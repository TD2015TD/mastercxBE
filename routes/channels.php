<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

// Global channels - all authenticated users can listen
Broadcast::channel('agents', function ($user) {
    return true; // All authenticated users can listen to agent updates
});

Broadcast::channel('queues', function ($user) {
    return true; // All authenticated users can listen to queue updates
});

Broadcast::channel('tickets', function ($user) {
    return true; // All authenticated users can listen to ticket updates
});

// Individual agent channel - only the agent or supervisors can listen
Broadcast::channel('agent.{agentId}', function ($user, $agentId) {
    return (int) $user->id === (int) $agentId || $user->hasRole('supervisor');
});

// Individual queue channel
Broadcast::channel('queue.{queueId}', function ($user, $queueId) {
    return true; // All authenticated users can listen to specific queue updates
});

// Individual conversation channel - only the agent handling the conversation
Broadcast::channel('conversation.{conversationId}', function ($user, $conversationId) {
    // Add logic to check if user is handling this conversation
    return true;
});

// Supervisor channel - only supervisors
Broadcast::channel('supervisor.{supervisorId}', function ($user, $supervisorId) {
    return (int) $user->id === (int) $supervisorId && $user->hasRole('supervisor');
});

// WebRTC call signaling channel — private to the target user, used to
// deliver call offers/answers/ICE candidates/hangup directly to them.
Broadcast::channel('signaling.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

// Presence channel — every logged-in user who has the app open joins this,
// so the P2P call directory can tell who's actually reachable right now
// instead of just who exists in the users table.
Broadcast::channel('online-callers', function ($user) {
    return ['id' => $user->id, 'name' => $user->name];
});
