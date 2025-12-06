<?php

namespace App\Services;

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
                'agent_id' => $ticket->assigned_agent_id,
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

            // Create escalation record
            $escalation = Escalation::create([
                'ticket_id' => $ticket->id,
                'escalated_by' => null, // System escalation
                'escalation_level' => $this->determineEscalationLevel($ticket),
                'reason' => $reason,
                'escalation_date' => now(),
                'status' => 'Open',
                'priority' => $ticket->priority,
                'department' => $ticket->department,
                'notes' => 'Auto-escalated due to SLA breach',
            ]);

            // Update ticket status
            $ticket->update([
                'status' => 'Escalated',
            ]);

            // Add to ticket history
            $ticket->histories()->create([
                'user_id' => null,
                'action' => 'escalated',
                'field_name' => 'status',
                'old_value' => $ticket->status,
                'new_value' => 'Escalated',
                'notes' => "Auto-escalated: {$reason}",
            ]);

            // Notify escalation team
            $this->notifySupervisor($ticket, 'escalation', [
                'message' => "Ticket #{$ticket->ticket_number} auto-escalated due to SLA breach",
                'escalation_level' => $escalation->escalation_level,
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
        // Higher priority tickets get higher escalation levels
        $levels = [
            'P1' => 'Level 3', // Critical - highest escalation
            'P2' => 'Level 2', // High
            'P3' => 'Level 1', // Medium
            'P4' => 'Level 1', // Low
        ];

        return $levels[$ticket->priority] ?? 'Level 1';
    }
}
