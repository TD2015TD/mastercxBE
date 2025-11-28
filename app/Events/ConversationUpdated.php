<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ConversationUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public array $conversationData;

    /**
     * Create a new event instance.
     */
    public function __construct(array $conversationData)
    {
        $this->conversationData = $conversationData;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('agent.' . $this->conversationData['agent_id']),
            new Channel('conversation.' . $this->conversationData['conversation_id']),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'conversation.updated';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'agent_id' => $this->conversationData['agent_id'],
            'conversation_id' => $this->conversationData['conversation_id'],
            'action' => $this->conversationData['action'] ?? null,
            'state' => $this->conversationData['state'] ?? null,
            'media_type' => $this->conversationData['media_type'] ?? null,
            'timestamp' => $this->conversationData['timestamp'] ?? now()->toISOString(),
            'details' => $this->conversationData['details'] ?? []
        ];
    }
}
