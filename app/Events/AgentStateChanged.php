<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AgentStateChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public array $stateData;

    /**
     * Create a new event instance.
     */
    public function __construct(array $stateData)
    {
        $this->stateData = $stateData;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('agent.' . $this->stateData['agent_id']),
            new Channel('agents'), // Broadcast to all agents channel
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'agent.state.changed';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'agent_id' => $this->stateData['agent_id'],
            'state' => $this->stateData['state'] ?? null,
            'reason' => $this->stateData['reason'] ?? null,
            'timestamp' => $this->stateData['timestamp'] ?? now()->toISOString(),
            'additional_data' => $this->stateData['additional_data'] ?? []
        ];
    }
}
