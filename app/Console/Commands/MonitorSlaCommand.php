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
            // Get all active tickets (not closed, resolved, or cancelled)
            $activeTickets = Ticket::whereNotIn('status', ['Closed', 'Resolved', 'Cancelled'])
                ->whereNotNull('sla_due_at')
                ->get();

            $this->info("📊 Found {$activeTickets->count()} active tickets to monitor");

            $breachedCount = 0;
            $warningCount = 0;
            $criticalCount = 0;
            $updatedCount = 0;

            foreach ($activeTickets as $ticket) {
                $result = $this->monitorTicket($ticket);
                
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

        $now = Carbon::now();
        $slaDeadline = Carbon::parse($ticket->sla_due_at);
        $remainingMinutes = $now->diffInMinutes($slaDeadline, false);
        $totalDuration = $ticket->sla_duration_minutes;

        // Calculate percentage of time elapsed
        $timeElapsed = $totalDuration - $remainingMinutes;
        $percentageElapsed = ($timeElapsed / $totalDuration) * 100;

        if ($this->option('debug')) {
            $this->line("Ticket #{$ticket->ticket_number}: {$remainingMinutes}min remaining ({$percentageElapsed}% elapsed)");
        }

        // Update remaining time
        $oldRemaining = $ticket->sla_remaining_minutes;
        $ticket->sla_remaining_minutes = (int) $remainingMinutes;

        // Check for SLA breach
        if ($remainingMinutes < 0) {
            if (!$ticket->sla_breached) {
                $ticket->sla_breached = true;
                $result['updated'] = true;
                $result['status'] = 'breached';

                $this->warn("🔴 Ticket #{$ticket->ticket_number} SLA BREACHED! Overdue by " . abs($remainingMinutes) . " minutes");

                // Send breach notification
                $this->slaAlertService->notifySlaBreach($ticket);

                // Auto-escalate if configured
                if ($this->shouldAutoEscalate($ticket)) {
                    $this->slaAlertService->escalateTicket($ticket, 'SLA Breach');
                }
            } else {
                $result['status'] = 'breached';
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
     */
    protected function shouldAutoEscalate(Ticket $ticket): bool
    {
        // Get SLA config for this ticket
        $slaConfig = \App\Models\SlaConfiguration::getSlaForTicket(
            $ticket->department,
            $ticket->priority
        );

        if ($slaConfig && $slaConfig->auto_escalate) {
            // Check if already escalated
            return !$ticket->escalations()->exists();
        }

        return false;
    }
}
