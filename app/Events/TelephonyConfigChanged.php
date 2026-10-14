<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast the moment an admin flips TELEPHONY_ENABLED, so every already-open
 * agent tab reacts instantly instead of waiting on the mitelConfigService poll
 * (see TelephonyBar.jsx/UserProfileDropdown.jsx/EmployeeInbox.jsx — those keep
 * polling too, as a fallback if this WebSocket connection ever drops).
 *
 * ShouldBroadcastNow (not ShouldBroadcast) is deliberate: this app's queue
 * table has a real, untouched backlog (113 jobs, confirmed directly) with no
 * worker running to drain it. Queuing this event would mean it never actually
 * broadcasts until someone starts a worker — and starting one blind would
 * also fire that entire backlog at once, which is a separate decision this
 * fix has no business making on its own. Broadcasting synchronously here
 * sidesteps the queue table completely.
 */
class TelephonyConfigChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public bool $enabled;

    public function __construct(bool $enabled)
    {
        $this->enabled = $enabled;
    }

    /**
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        // Plain public channel — every authenticated agent needs to know this
        // flag's value, none of it is per-user sensitive data, so there's no
        // reason to require the private-channel auth handshake for it.
        return [new Channel('telephony-config')];
    }

    public function broadcastAs(): string
    {
        return 'telephony.config.changed';
    }

    public function broadcastWith(): array
    {
        return ['enabled' => $this->enabled];
    }
}
