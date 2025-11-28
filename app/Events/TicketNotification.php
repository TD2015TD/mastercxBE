<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TicketNotification implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public array $ticketData;

    /**
     * Create a new event instance.
     */
    public function __construct(array $ticketData)
    {
        $this->ticketData = $ticketData;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        $channels = [
            new Channel('tickets'), // Broadcast to all tickets channel
        ];

        // If assigned to specific agent, broadcast to their channel
        if (!empty($this->ticketData['assigned_to'])) {
            $channels[] = new Channel('agent.' . $this->ticketData['assigned_to']);
        }

        // If ticket has supervisor, broadcast to supervisor channel
        if (!empty($this->ticketData['supervisor_id'])) {
            $channels[] = new Channel('supervisor.' . $this->ticketData['supervisor_id']);
        }

        return $channels;
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'ticket.notification';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'ticket_id' => $this->ticketData['ticket_id'],
            'ticket_number' => $this->ticketData['ticket_number'] ?? null,
            'event_type' => $this->ticketData['event_type'] ?? 'updated', // created, updated, assigned, escalated, resolved
            'priority' => $this->ticketData['priority'] ?? null,
            'status' => $this->ticketData['status'] ?? null,
            'assigned_to' => $this->ticketData['assigned_to'] ?? null,
            'subject' => $this->ticketData['subject'] ?? null,
            'timestamp' => $this->ticketData['timestamp'] ?? now()->toISOString(),
            'message' => $this->ticketData['message'] ?? null
        ];
    }
}
