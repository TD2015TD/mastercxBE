<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Support\Facades\Log;

class SignalingEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public int $targetUserId,
        public int $fromUserId,
        public string $fromUserName,
        public string $type,
        public array $payload,
    ) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('signaling.' . $this->targetUserId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'signal';
    }

    public function broadcastWith(): array
    {
        // Temporary diagnostic — see SignalingController for context.
        if (in_array($this->type, ['offer', 'answer'], true)) {
            $sdp = $this->payload['sdp'] ?? null;
            Log::info('[SignalingEvent] broadcasting sdp', [
                'type' => $this->type,
                'sdp_length' => is_string($sdp) ? strlen($sdp) : null,
                'sdp_line_count' => is_string($sdp) ? substr_count($sdp, "\n") : null,
                'sdp_json' => json_encode($sdp),
            ]);
        }

        return [
            'fromUserId' => $this->fromUserId,
            'fromUserName' => $this->fromUserName,
            'type' => $this->type,
            'payload' => $this->payload,
        ];
    }
}
