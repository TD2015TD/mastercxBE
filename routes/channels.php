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
