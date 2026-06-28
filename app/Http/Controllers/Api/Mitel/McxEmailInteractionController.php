<?php

namespace App\Http\Controllers\Api\Mitel;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Services\McxOpenMediaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Manual trigger / retry endpoints for MCX Open Media email interactions.
 *
 * These are primarily for support tooling, diagnostics, and back-fills.
 * Normal creation and closure is handled automatically by TicketObserver.
 */
class McxEmailInteractionController extends Controller
{
    public function __construct(private McxOpenMediaService $mcx) {}

    // -------------------------------------------------------------------------
    // POST /api/v1/mitel/mcx-email/{ticketId}/send
    // -------------------------------------------------------------------------

    /**
     * Manually submit (or re-submit) a ticket as a MCX Open Media interaction.
     *
     * Useful when the automatic observer call failed on creation.
     * Overwrites any previously stored mitel_interaction_id.
     */
    public function send(string $ticketId): JsonResponse
    {
        $ticket = Ticket::find($ticketId);

        if (!$ticket) {
            return response()->json(['success' => false, 'message' => 'Ticket not found'], 404);
        }

        if ($ticket->channel !== 'email') {
            return response()->json([
                'success' => false,
                'message' => "Ticket channel is '{$ticket->channel}', expected 'email'",
            ], 422);
        }

        try {
            $fromAddress = $this->resolveFromAddress($ticket);

            $mitelId = $this->mcx->sendInteraction(
                interactionId: $ticket->ticket_number,
                subject:       $ticket->subject,
                fromAddress:   $fromAddress,
                bodyText:      $ticket->description ?? ''
            );

            Ticket::withoutEvents(function () use ($ticket, $mitelId) {
                $ticket->mitel_interaction_id = $mitelId;
                $ticket->save();
            });

            return response()->json([
                'success'              => true,
                'message'              => 'MCX interaction submitted',
                'mitel_interaction_id' => $mitelId,
                'ticket_number'        => $ticket->ticket_number,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to submit MCX interaction: ' . $e->getMessage(),
            ], 502);
        }
    }

    // -------------------------------------------------------------------------
    // POST /api/v1/mitel/mcx-email/{ticketId}/close
    // -------------------------------------------------------------------------

    /**
     * Manually close the MCX interaction for a ticket.
     *
     * Accepts an optional JSON body: { "reason": "Resolved" }
     */
    public function close(Request $request, string $ticketId): JsonResponse
    {
        $ticket = Ticket::find($ticketId);

        if (!$ticket) {
            return response()->json(['success' => false, 'message' => 'Ticket not found'], 404);
        }

        if (!$ticket->mitel_interaction_id) {
            return response()->json([
                'success' => false,
                'message' => 'No mitel_interaction_id stored on this ticket',
            ], 422);
        }

        try {
            $reason = $request->input('reason', $ticket->status);
            $this->mcx->closeInteraction($ticket->mitel_interaction_id, $reason);

            return response()->json([
                'success'              => true,
                'message'              => 'MCX interaction closed',
                'mitel_interaction_id' => $ticket->mitel_interaction_id,
                'ticket_number'        => $ticket->ticket_number,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to close MCX interaction: ' . $e->getMessage(),
            ], 502);
        }
    }

    // -------------------------------------------------------------------------
    // GET /api/v1/mitel/mcx-email/{ticketId}/status
    // -------------------------------------------------------------------------

    /**
     * Return the MCX interaction ID stored for a ticket (useful for diagnostics).
     */
    public function status(string $ticketId): JsonResponse
    {
        $ticket = Ticket::find($ticketId);

        if (!$ticket) {
            return response()->json(['success' => false, 'message' => 'Ticket not found'], 404);
        }

        return response()->json([
            'success'              => true,
            'ticket_number'        => $ticket->ticket_number,
            'ticket_status'        => $ticket->status,
            'channel'              => $ticket->channel,
            'mitel_interaction_id' => $ticket->mitel_interaction_id,
        ]);
    }

    // -------------------------------------------------------------------------

    private function resolveFromAddress(Ticket $ticket): string
    {
        $emailMessage = \App\Models\EmailMessage::where('ticket_id', $ticket->id)
            ->orderBy('id', 'desc')
            ->first();

        if ($emailMessage?->from_email) {
            return $emailMessage->from_email;
        }

        return $ticket->customer?->email ?? 'unknown@crm.local';
    }
}
