<?php

namespace App\Console\Commands;

use App\Models\Ticket;
use App\Services\SlaAlertService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class MonitorSlaCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sla:monitor 
                            {--force : Force monitoring even if already running}
                            {--debug : Enable debug output}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Monitor SLA status for all active tickets and trigger alerts';

    protected $slaAlertService;

    /**
     * Create a new command instance.
     */
    public function __construct(SlaAlertService $slaAlertService)
    {
        parent::__construct();
        $this->slaAlertService = $slaAlertService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $startTime = now();
        $this->info("🔍 SLA Monitor started at {$startTime->format('Y-m-d H:i:s')}");

        try {
            // Get all active tickets (not closed, resolved, or cancelled).
            // Tickets whose clock is paused (On Hold / Pending) are deliberately excluded —
            // their remaining time must not move and they must not breach while frozen.
            $activeTickets = Ticket::whereNotIn('status', Ticket::SLA_STOPPED_STATUSES)
                ->whereNotNull('sla_due_at')
                ->whereNull('sla_paused_at')
                ->get();

            $this->info("📊 Found {$activeTickets->count()} active tickets to monitor");

            $breachedCount = 0;
            $warningCount = 0;
            $criticalCount = 0;
            $updatedCount = 0;

            $failedCount = 0;

            foreach ($activeTickets as $ticket) {
                // Isolate per ticket: a single failing ticket (bad notification target, a
                // constraint violation downstream) must not abort SLA monitoring for the rest.
                try {
                    $result = $this->monitorTicket($ticket);
                } catch (\Throwable $e) {
                    $failedCount++;
                    $this->error("⚠️  Ticket #{$ticket->ticket_number} could not be monitored: {$e->getMessage()}");
                    Log::error('SLA monitoring failed for ticket', [
                        'ticket_id' => $ticket->id,
                        'ticket_number' => $ticket->ticket_number,
                        'error' => $e->getMessage(),
                    ]);
                    continue;
                }

                if ($result['updated']) {
                    $updatedCount++;
                }
                
                switch ($result['status']) {
                    case 'breached':
                        $breachedCount++;
                        break;
                    case 'critical':
                        $criticalCount++;
                        break;
                    case 'warning':
                        $warningCount++;
                        break;
                }
            }

            $duration = now()->diffInSeconds($startTime);

            // Summary
            $this->newLine();
            $this->info("✅ SLA Monitor completed in {$duration} seconds");
            $this->table(
                ['Status', 'Count'],
                [
                    ['🔴 Breached', $breachedCount],
                    ['🟠 Critical (>90%)', $criticalCount],
                    ['🟡 Warning (>80%)', $warningCount],
                    ['📝 Updated', $updatedCount],
                    ['⚠️  Failed', $failedCount],
                    ['📊 Total Checked', $activeTickets->count()],
                ]
            );

            // Log summary
            Log::info('SLA Monitor completed', [
                'duration_seconds' => $duration,
                'total_tickets' => $activeTickets->count(),
                'breached' => $breachedCount,
                'critical' => $criticalCount,
                'warning' => $warningCount,
                'updated' => $updatedCount,
                'failed' => $failedCount,
            ]);

            return 0;
        } catch (\Exception $e) {
            $this->error("❌ Error during SLA monitoring: {$e->getMessage()}");
            Log::error('SLA Monitor failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return 1;
        }
    }

    /**
     * Monitor individual ticket SLA.
     */
    protected function monitorTicket(Ticket $ticket): array
    {
        $result = [
            'updated' => false,
            'status' => 'ok',
        ];

        $oldRemaining = $ticket->sla_remaining_minutes;
        $wasBreached = (bool) $ticket->sla_breached;

        // Single source of truth for the countdown and the breach flag.
        $ticket->updateSlaRemaining();

        $remainingMinutes = (int) $ticket->sla_remaining_minutes;
        $totalDuration = (int) $ticket->sla_duration_minutes;

        // Calculate percentage of time elapsed. Guard the divisor: a ticket with a missing or
        // zero duration would otherwise raise a division-by-zero and abort the whole run.
        $percentageElapsed = $totalDuration > 0
            ? (($totalDuration - $remainingMinutes) / $totalDuration) * 100
            : ($remainingMinutes < 0 ? 100 : 0);

        if ($this->option('debug')) {
            $this->line("Ticket #{$ticket->ticket_number}: {$remainingMinutes}min remaining ({$percentageElapsed}% elapsed)");
        }

        // Check for SLA breach
        if ($remainingMinutes < 0) {
            $result['status'] = 'breached';

            if (!$wasBreached) {
                $result['updated'] = true;

                $this->warn("🔴 Ticket #{$ticket->ticket_number} SLA BREACHED! Overdue by " . abs($remainingMinutes) . " minutes");

                // Send breach notification — once, right at the transition, same as
                // before. Unlike escalation below, spamming this every 15 minutes
                // would just be noise.
                $this->slaAlertService->notifySlaBreach($ticket);
            }

            // Auto-escalate if configured. Deliberately NOT gated on !$wasBreached —
            // confirmed directly as a real bug: a ticket whose FIRST breached run
            // couldn't escalate (SLA rule not fully configured yet, no active
            // supervisor at that moment, etc.) had sla_breached permanently set to
            // true on that same run, so !$wasBreached was false on every run after
            // that forever — no auto-escalation ever fired again for it, even once
            // the underlying problem was fixed. shouldAutoEscalate() already guards
            // against re-escalating a ticket that has one (escalations()->exists()),
            // so retrying every run here is safe and self-heals a ticket stuck in
            // exactly this state instead of needing a human to notice and manually
            // escalate it.
            if ($this->shouldAutoEscalate($ticket)) {
                $this->slaAlertService->escalateTicket($ticket, 'SLA Breach');
                $result['updated'] = true;
            }
        }
        // Critical warning (>90% of time elapsed)
        elseif ($percentageElapsed >= 90) {
            $result['status'] = 'critical';
            
            // Send critical alert only once when crossing threshold
            if ($this->shouldSendAlert($ticket, 'critical', $percentageElapsed)) {
                $this->error("🟠 Ticket #{$ticket->ticket_number} CRITICAL: {$remainingMinutes}min remaining!");
                $this->slaAlertService->notifySlaCritical($ticket, $remainingMinutes);
                $this->markAlertSent($ticket, 'critical');
            }
        }
        // Warning (>80% of time elapsed)
        elseif ($percentageElapsed >= 80) {
            $result['status'] = 'warning';

            // Send warning alert only once when crossing threshold
            if ($this->shouldSendAlert($ticket, 'warning', $percentageElapsed)) {
                $this->comment("🟡 Ticket #{$ticket->ticket_number} WARNING: {$remainingMinutes}min remaining");
                $this->slaAlertService->notifySlaWarning($ticket, $remainingMinutes);
                $this->markAlertSent($ticket, 'warning');
            }
        }

        // Auto-escalate once the ticket crosses its OWN configured escalation
        // threshold (SlaConfiguration::escalation_threshold_percent, e.g. 80%) —
        // independent of the hardcoded 80/90 bands above, which only drive alert
        // *notifications*, not escalation. Previously shouldAutoEscalate() was
        // only ever called from the full-breach branch above, so a ticket never
        // escalated at its configured threshold, only after its SLA had already
        // fully lapsed.
        if ($remainingMinutes >= 0 && $this->shouldAutoEscalate($ticket, $percentageElapsed)) {
            $this->warn("🟠 Ticket #{$ticket->ticket_number} crossed its escalation threshold ({$percentageElapsed}% elapsed) — auto-escalating");
            $this->slaAlertService->escalateTicket($ticket, 'SLA Escalation Threshold Crossed');
            $result['updated'] = true;
        }

        // Save if remaining time changed
        if ($oldRemaining !== $ticket->sla_remaining_minutes || $result['updated']) {
            $ticket->save();
            $result['updated'] = true;
        }

        return $result;
    }

    /**
     * Check if alert should be sent (prevent duplicate alerts).
     */
    protected function shouldSendAlert(Ticket $ticket, string $level, float $percentageElapsed): bool
    {
        $cacheKey = "sla_alert_{$ticket->id}_{$level}";
        
        // Check if alert already sent in last 4 hours
        if (cache()->has($cacheKey)) {
            return false;
        }

        // For warning level, only alert once at 80-90%
        if ($level === 'warning' && $percentageElapsed >= 90) {
            return false;
        }

        return true;
    }

    /**
     * Mark alert as sent.
     */
    protected function markAlertSent(Ticket $ticket, string $level): void
    {
        $cacheKey = "sla_alert_{$ticket->id}_{$level}";
        cache()->put($cacheKey, true, now()->addHours(4));
    }

    /**
     * Check if ticket should be auto-escalated.
     *
     * $percentageElapsed omitted (null) = called from the full-breach branch,
     * where crossing 100% always qualifies. Passed = called from the pre-breach
     * check, where escalation only qualifies once elapsed time reaches this
     * SLA config's own escalation_threshold_percent (not the fixed 80/90 alert
     * bands, which are a separate, unrelated concept).
     */
    protected function shouldAutoEscalate(Ticket $ticket, ?float $percentageElapsed = null): bool
    {
        // Get SLA config for this ticket
        $slaConfig = \App\Models\SlaConfiguration::getSlaForTicket(
            $ticket->department,
            $ticket->priority
        );

        if (!$slaConfig || !$slaConfig->auto_escalate) {
            return false;
        }

        // Already escalated — don't do it again.
        if ($ticket->escalations()->exists()) {
            return false;
        }

        if ($percentageElapsed === null) {
            return true;
        }

        $threshold = (float) ($slaConfig->escalation_threshold_percent ?? 100);

        return $percentageElapsed >= $threshold;
    }
}
