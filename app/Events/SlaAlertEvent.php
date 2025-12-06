<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SlaAlertEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $channel;
    public $data;

    /**
     * Create a new event instance.
     */
    public function __construct(string $channel, array $data)
    {
        $this->channel = $channel;
        $this->data = $data;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel($this->channel),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'sla.alert';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'type' => $this->data['type'] ?? 'sla_alert',
            'ticket_id' => $this->data['ticket_id'] ?? null,
            'ticket_number' => $this->data['ticket_number'] ?? null,
            'priority' => $this->data['priority'] ?? null,
            'remaining_minutes' => $this->data['remaining_minutes'] ?? null,
            'overdue_minutes' => $this->data['overdue_minutes'] ?? null,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
