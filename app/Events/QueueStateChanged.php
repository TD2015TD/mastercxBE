<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class QueueStateChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public array $queueData;

    /**
     * Create a new event instance.
     */
    public function __construct(array $queueData)
    {
        $this->queueData = $queueData;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('queue.' . $this->queueData['queue_id']),
            new Channel('queues'), // Broadcast to all queues channel
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'queue.state.changed';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'queue_id' => $this->queueData['queue_id'],
            'queue_name' => $this->queueData['queue_name'] ?? null,
            'waiting_count' => $this->queueData['waiting_count'] ?? 0,
            'available_agents' => $this->queueData['available_agents'] ?? 0,
            'longest_wait_time' => $this->queueData['longest_wait_time'] ?? 0,
            'timestamp' => $this->queueData['timestamp'] ?? now()->toISOString(),
            'statistics' => $this->queueData['statistics'] ?? []
        ];
    }
}
