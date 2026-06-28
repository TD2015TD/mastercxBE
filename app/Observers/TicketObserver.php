<?php

namespace App\Observers;

use App\Models\Ticket;
use App\Services\McxOpenMediaService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Bridges Ticket lifecycle events to the Mitel MCX Open Media v2 API.
 *
 * created  → POST interaction for email-channel tickets
 * updated  → POST close when status moves to Closed/Resolved and a
 *             mitelInteractionId is already stored
 */
class TicketObserver
{
    public function __construct(private McxOpenMediaService $mcx) {}

    // -------------------------------------------------------------------------
    // Ticket created
    // -------------------------------------------------------------------------

    public function created(Ticket $ticket): void
    {
        if ($ticket->channel !== 'email') {
            return;
        }

        // Ensure every email ticket has a case_id for tracking/display
        if (!$ticket->case_id) {
            Ticket::withoutEvents(function () use ($ticket) {
                $ticket->case_id = (string) Str::uuid();
                $ticket->save();
            });
        }

        // For email-channel tickets the Mitel interaction is created by
        // ProcessIncomingEmail::pushEmailToMitel (which saves mitel_interaction_id).
        // Creating a second interaction here would leave an unclosed duplicate in
        // the Mitel queue and cause the "Abandoned" status in Ignite History.
        // TicketObserver::updated still handles closing when the ticket is resolved.
    }

    // -------------------------------------------------------------------------
    // Ticket updated
    // -------------------------------------------------------------------------

    public function updated(Ticket $ticket): void
    {
        if (!$ticket->mitel_interaction_id) {
            return;
        }

        if (!$ticket->isDirty('status')) {
            return;
        }

        $newStatus = $ticket->status;
        $oldStatus = $ticket->getOriginal('status');

        $terminalStatuses = ['Closed', 'Resolved', 'Cancelled'];

        if (!in_array($newStatus, $terminalStatuses)) {
            return;
        }

        // Already was terminal — avoid duplicate close calls
        if (in_array($oldStatus, $terminalStatuses)) {
            return;
        }

        try {
            $this->mcx->closeInteraction($ticket->mitel_interaction_id, $newStatus);
        } catch (\Throwable $e) {
            Log::error('[TicketObserver] Failed to close MCX interaction on ticket status change', [
                'ticket_id'          => $ticket->id,
                'ticket_number'      => $ticket->ticket_number,
                'mitel_interaction_id' => $ticket->mitel_interaction_id,
                'new_status'         => $newStatus,
                'error'              => $e->getMessage(),
            ]);
        }
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Return the best "from" address for the interaction.
     *
     * Checks the most recent EmailMessage linked to this ticket; falls back to
     * the customer's email, then to a generic placeholder.
     */
    private function resolveFromAddress(Ticket $ticket): string
    {
        $emailMessage = \App\Models\EmailMessage::where('ticket_id', $ticket->id)
            ->orderBy('id', 'desc')
            ->first();

        if ($emailMessage?->from_email) {
            return $emailMessage->from_email;
        }

        if ($ticket->customer?->email) {
            return $ticket->customer->email;
        }

        return 'unknown@crm.local';
    }
}
