<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\Ticket;
use App\Models\Escalation;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class SlaAlertService
{
    /**
     * Notify when SLA is breached.
     */
    public function notifySlaBreach(Ticket $ticket): void
    {
        try {
            Log::warning('SLA Breach Detected', [
                'ticket_id' => $ticket->id,
                'ticket_number' => $ticket->ticket_number,
                'agent_id' => $ticket->assigned_to,
                'department' => $ticket->department,
                'priority' => $ticket->priority,
                'sla_due_at' => $ticket->sla_due_at,
                'overdue_minutes' => abs($ticket->sla_remaining_minutes),
            ]);

            // Notify assigned agent
            if ($ticket->assignedAgent) {
                $this->notifyAgent($ticket, 'breach', [
                    'message' => "SLA BREACHED for Ticket #{$ticket->ticket_number}",
                    'overdue_by' => abs($ticket->sla_remaining_minutes) . ' minutes',
                ]);
            }

            // Notify supervisor
            $this->notifySupervisor($ticket, 'breach', [
                'message' => "SLA BREACHED - Ticket #{$ticket->ticket_number}",
                'agent' => $ticket->assignedAgent?->user?->name ?? 'Unassigned',
                'overdue_by' => abs($ticket->sla_remaining_minutes) . ' minutes',
            ]);

            // Broadcast via WebSocket
            $this->broadcastSlaAlert($ticket, 'breach', [
                'type' => 'sla_breach',
                'ticket_id' => $ticket->id,
                'ticket_number' => $ticket->ticket_number,
                'priority' => $ticket->priority,
                'overdue_minutes' => abs($ticket->sla_remaining_minutes),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send SLA breach notification', [
                'ticket_id' => $ticket->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Notify when SLA is critical (>90% elapsed).
     */
    public function notifySlaCritical(Ticket $ticket, int $remainingMinutes): void
    {
        try {
            Log::warning('SLA Critical Warning', [
                'ticket_id' => $ticket->id,
                'ticket_number' => $ticket->ticket_number,
                'remaining_minutes' => $remainingMinutes,
            ]);

            // Notify assigned agent
            if ($ticket->assignedAgent) {
                $this->notifyAgent($ticket, 'critical', [
                    'message' => "URGENT: Ticket #{$ticket->ticket_number} SLA expiring soon!",
                    'remaining' => $remainingMinutes . ' minutes',
                ]);
            }

            // Broadcast via WebSocket
            $this->broadcastSlaAlert($ticket, 'critical', [
                'type' => 'sla_critical',
                'ticket_id' => $ticket->id,
                'ticket_number' => $ticket->ticket_number,
                'priority' => $ticket->priority,
                'remaining_minutes' => $remainingMinutes,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send SLA critical notification', [
                'ticket_id' => $ticket->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Notify when SLA warning threshold reached (>80% elapsed).
     */
    public function notifySlaWarning(Ticket $ticket, int $remainingMinutes): void
    {
        try {
            Log::info('SLA Warning', [
                'ticket_id' => $ticket->id,
                'ticket_number' => $ticket->ticket_number,
                'remaining_minutes' => $remainingMinutes,
            ]);

            // Notify assigned agent
            if ($ticket->assignedAgent) {
                $this->notifyAgent($ticket, 'warning', [
                    'message' => "Ticket #{$ticket->ticket_number} approaching SLA deadline",
                    'remaining' => $remainingMinutes . ' minutes',
                ]);
            }

            // Broadcast via WebSocket
            $this->broadcastSlaAlert($ticket, 'warning', [
                'type' => 'sla_warning',
                'ticket_id' => $ticket->id,
                'ticket_number' => $ticket->ticket_number,
                'priority' => $ticket->priority,
                'remaining_minutes' => $remainingMinutes,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send SLA warning notification', [
                'ticket_id' => $ticket->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Escalate ticket due to SLA breach.
     */
    public function escalateTicket(Ticket $ticket, string $reason): void
    {
        try {
            Log::info('Auto-escalating ticket due to SLA breach', [
                'ticket_id' => $ticket->id,
                'ticket_number' => $ticket->ticket_number,
                'reason' => $reason,
            ]);

            $toAgent = $this->resolveEscalationTarget($ticket);

            if (!$toAgent) {
                // A log line nobody watches in real time is a silent failure in every way that
                // matters to an admin. Put it somewhere a human will actually see it: the ticket's
                // own history, and the audit log.
                Log::warning('Cannot auto-escalate ticket: no supervisor agent found', [
                    'ticket_id' => $ticket->id,
                    'department' => $ticket->department,
                ]);

                $ticket->histories()->create([
                    'user_id' => null,
                    'action' => 'escalation_failed',
                    'field_name' => null,
                    'old_value' => null,
                    'new_value' => null,
                    'description' => "Auto-escalation due to SLA breach could not run: no active supervisor found for department \"{$ticket->department}\".",
                ]);

                \App\Services\AuditLogger::warning(
                    'Auto-Escalation Failed',
                    'SLA Engine',
                    'Escalation Management',
                    "Ticket {$ticket->ticket_number} breached SLA but could not be auto-escalated — no active supervisor is assigned to department \"{$ticket->department}\".",
                    ['ticket_id' => $ticket->id, 'ticket_number' => $ticket->ticket_number, 'department' => $ticket->department]
                );

                return;
            }

            // Create escalation record. Field names must match the escalations table exactly —
            // this previously used escalation_level/escalation_date/priority/department/status
            // values that don't exist on this table (or don't match its enums), so every
            // auto-escalation attempt failed silently (caught below, logged, nothing created).
            $escalation = Escalation::create([
                'ticket_id' => $ticket->id,
                'from_agent_id' => $ticket->assigned_to,
                'to_agent_id' => $toAgent->id,
                'escalated_by' => null, // System escalation — column is nullable for this reason
                'level' => $this->determineEscalationLevel($ticket),
                'reason' => $reason,
                'notes' => 'Auto-escalated due to SLA breach',
                'escalation_type' => 'auto',
                'status' => 'pending',
                'escalated_at' => now(),
            ]);

            // The ticket status is deliberately left alone. 'Escalated' is not a member of the
            // tickets.status check constraint, so writing it threw on every auto-escalation and
            // aborted the surrounding sla:monitor transaction — taking breach detection down
            // with it. Escalation state belongs to the escalations table, which is also how the
            // manual escalation flow models it.
            $ticket->histories()->create([
                'user_id' => null,
                'action' => 'escalated',
                'field_name' => 'escalation',
                'old_value' => null,
                'new_value' => $escalation->level,
                'description' => "Auto-escalated: {$reason}",
            ]);

            // Notify the specific target so they can discover the escalation without already
            // knowing which ticket it's on, plus the broader supervisor team for visibility.
            if ($toAgent->user) {
                $toAgent->user->notify(new \App\Notifications\EscalationNotification($escalation));
            }

            $this->notifySupervisor($ticket, 'escalation', [
                'message' => "Ticket #{$ticket->ticket_number} auto-escalated due to SLA breach",
                'escalation_level' => $escalation->level,
            ]);

            Log::info('Ticket escalated successfully', [
                'ticket_id' => $ticket->id,
                'escalation_id' => $escalation->id,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to escalate ticket', [
                'ticket_id' => $ticket->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Notify agent about SLA alert.
     */
    protected function notifyAgent(Ticket $ticket, string $level, array $data): void
    {
        try {
            $agent = $ticket->assignedAgent;
            
            if (!$agent || !$agent->user) {
                return;
            }

            // Create in-app notification
            $agent->user->notify(new \App\Notifications\SlaAlertNotification([
                'ticket' => $ticket,
                'level' => $level,
                'data' => $data,
            ]));

            Log::info('Agent notified about SLA alert', [
                'agent_id' => $agent->id,
                'user_id' => $agent->user_id,
                'ticket_id' => $ticket->id,
                'level' => $level,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to notify agent', [
                'ticket_id' => $ticket->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Notify supervisor about SLA alert.
     */
    protected function notifySupervisor(Ticket $ticket, string $level, array $data): void
    {
        try {
            // Get supervisors for the department
            $supervisors = \App\Models\User::whereHas('role', function ($query) {
                $query->where('slug', 'supervisor');
            })->get();

            foreach ($supervisors as $supervisor) {
                $supervisor->notify(new \App\Notifications\SlaAlertNotification([
                    'ticket' => $ticket,
                    'level' => $level,
                    'data' => $data,
                ]));
            }

            Log::info('Supervisors notified about SLA alert', [
                'ticket_id' => $ticket->id,
                'level' => $level,
                'supervisor_count' => $supervisors->count(),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to notify supervisors', [
                'ticket_id' => $ticket->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Broadcast SLA alert via WebSocket.
     */
    protected function broadcastSlaAlert(Ticket $ticket, string $level, array $data): void
    {
        try {
            // Broadcast to agent channel
            if ($ticket->assigned_agent_id) {
                broadcast(new \App\Events\SlaAlertEvent(
                    "agent.{$ticket->assigned_agent_id}",
                    $data
                ))->toOthers();
            }

            // Broadcast to supervisor channel
            broadcast(new \App\Events\SlaAlertEvent(
                'supervisor.dashboard',
                $data
            ))->toOthers();

            Log::info('SLA alert broadcasted', [
                'ticket_id' => $ticket->id,
                'level' => $level,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to broadcast SLA alert', [
                'ticket_id' => $ticket->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Determine escalation level based on ticket properties.
     */
    protected function determineEscalationLevel(Ticket $ticket): string
    {
        // Higher priority tickets get higher escalation levels. Values must match the
        // escalations.level enum exactly: L1, L2, Supervisor, Manager.
        $levels = [
            'P1' => 'Manager',    // Critical - highest escalation
            'P2' => 'Supervisor', // High
            'P3' => 'L2',         // Medium
            'P4' => 'L1',         // Low
        ];

        return $levels[$ticket->priority] ?? 'L1';
    }

    /**
     * Find the agent an auto-escalation should be routed to: a supervisor in the ticket's
     * own department. Deliberately does NOT fall back to a supervisor from a different
     * department — that silently misrouted escalations to whichever supervisor happened
     * to be first system-wide (QA-confirmed bug), which is worse than the caller's existing
     * "no target found" handling (logs to the ticket history + audit log, doesn't throw).
     */
    protected function resolveEscalationTarget(Ticket $ticket): ?Agent
    {
        // Case/whitespace-insensitive match, same reasoning as SlaConfiguration::byDepartment():
        // agents.department and tickets.department are free text, and a mismatched casing
        // between how an agent was set up and how the ticket's department is stored would make
        // a real, correctly-assigned supervisor invisible to this lookup.
        $departmentMatch = Agent::whereHas('user.role', fn ($q) => $q->where('slug', 'supervisor'))
            ->whereRaw('UPPER(TRIM(department)) = ?', [\Illuminate\Support\Str::upper(trim((string) $ticket->department))])
            ->where('status', 'active')
            ->first();

        if ($departmentMatch) {
            return $departmentMatch;
        }

        // No supervisor is assigned to this ticket's own department — confirmed directly as a
        // real gap on this tenant (a "SALES TAX" department with an active, auto-escalate SLA
        // config but zero supervisors in it). Previously this meant the breach was silently
        // dropped: no escalation record, no notification, nothing an admin would ever see short
        // of reading the ticket's own history. A breached ticket reaching NO ONE is worse than
        // reaching the wrong department's supervisor, so fall back to any active supervisor
        // instead of giving up — oldest-assigned first, for a stable, predictable choice rather
        // than whichever row the DB happens to return first.
        $fallback = Agent::whereHas('user.role', fn ($q) => $q->where('slug', 'supervisor'))
            ->where('status', 'active')
            ->orderBy('created_at')
            ->first();

        if ($fallback) {
            Log::warning('No supervisor found for ticket department — falling back to another active supervisor', [
                'ticket_id' => $ticket->id,
                'ticket_department' => $ticket->department,
                'fallback_agent_id' => $fallback->id,
                'fallback_agent_department' => $fallback->department,
            ]);
        }

        return $fallback;
    }
}
