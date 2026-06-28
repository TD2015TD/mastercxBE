<?php

namespace App\Console\Commands;

use App\Models\Ticket;
use App\Services\McxOpenMediaService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Polls Mitel API every minute to detect interactions that were closed by the
 * agent (hangup/handled) and auto-closes the linked CRM ticket.
 */
class SyncMitelInteractions extends Command
{
    protected $signature   = 'mitel:sync-interactions';
    protected $description = 'Close CRM tickets whose Mitel interaction has been handled/removed';

    public function __construct(private McxOpenMediaService $mcx)
    {
        parent::__construct();
    }

    public function handle(): void
    {
        $tickets = Ticket::whereNotNull('mitel_interaction_id')
            ->whereNotIn('status', ['Closed', 'Resolved', 'Cancelled'])
            ->get(['id', 'ticket_number', 'mitel_interaction_id', 'status']);

        if ($tickets->isEmpty()) {
            return;
        }

        foreach ($tickets as $ticket) {
            try {
                $state = $this->mcx->getInteractionState($ticket->mitel_interaction_id);

                // If interaction no longer exists or is Handled → close the ticket
                if ($state === null || $state === 'Handled') {
                    $ticket->close();
                    Log::info('[SyncMitelInteractions] Ticket closed — interaction handled/removed', [
                        'ticket' => $ticket->ticket_number,
                        'mitel_id' => $ticket->mitel_interaction_id,
                        'state' => $state ?? 'not found (404)',
                    ]);
                    $stateLabel = $state ?? 'not found (404)';
                    $this->line("Closed {$ticket->ticket_number} — interaction {$stateLabel}");
                }
            } catch (\Throwable $e) {
                Log::warning('[SyncMitelInteractions] Error checking interaction', [
                    'ticket' => $ticket->ticket_number,
                    'error'  => $e->getMessage(),
                ]);
            }
        }
    }
}
