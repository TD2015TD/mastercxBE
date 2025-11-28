<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Events\AgentStateChanged;
use App\Events\ConversationUpdated;
use App\Events\QueueStateChanged;
use App\Events\TicketNotification;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * WebSocketController - Handle WebSocket connections and broadcasting
 * 
 * Provides endpoints for:
 * - Testing WebSocket connections
 * - Manual event broadcasting
 * - WebSocket connection health checks
 */
class WebSocketController extends Controller
{
    /**
     * Test WebSocket connection and broadcasting
     * POST /api/v1/websocket/test
     */
    public function testBroadcast(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'event_type' => 'required|string|in:agent_state,conversation,queue,ticket',
            'data' => 'required|array'
        ]);

        try {
            $eventData = array_merge($validated['data'], [
                'timestamp' => now()->toISOString()
            ]);

            switch ($validated['event_type']) {
                case 'agent_state':
                    broadcast(new AgentStateChanged($eventData));
                    break;

                case 'conversation':
                    broadcast(new ConversationUpdated($eventData));
                    break;

                case 'queue':
                    broadcast(new QueueStateChanged($eventData));
                    break;

                case 'ticket':
                    broadcast(new TicketNotification($eventData));
                    break;
            }

            return response()->json([
                'success' => true,
                'message' => 'Test broadcast sent successfully',
                'event_type' => $validated['event_type'],
                'data' => $eventData
            ]);

        } catch (\Exception $e) {
            Log::error('WebSocket Test Broadcast Error', [
                'event_type' => $validated['event_type'],
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to broadcast test event',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get WebSocket connection info
     * GET /api/v1/websocket/info
     */
    public function getConnectionInfo(Request $request): JsonResponse
    {
        $config = [
            'driver' => config('broadcasting.default'),
            'connections' => [
                'pusher' => [
                    'enabled' => config('broadcasting.connections.pusher.key') !== null,
                    'key' => config('broadcasting.connections.pusher.key'),
                    'cluster' => config('broadcasting.connections.pusher.options.cluster')
                ],
                'reverb' => [
                    'enabled' => config('broadcasting.connections.reverb.key') !== null,
                    'host' => config('broadcasting.connections.reverb.options.host'),
                    'port' => config('broadcasting.connections.reverb.options.port')
                ],
                'redis' => [
                    'enabled' => config('broadcasting.connections.redis') !== null
                ]
            ]
        ];

        return response()->json([
            'success' => true,
            'data' => $config
        ]);
    }

    /**
     * Health check for WebSocket server
     * GET /api/v1/websocket/health
     */
    public function healthCheck(Request $request): JsonResponse
    {
        try {
            $driver = config('broadcasting.default');
            $isConfigured = !empty(config("broadcasting.connections.{$driver}.key"));

            return response()->json([
                'success' => true,
                'status' => $isConfigured ? 'configured' : 'not_configured',
                'driver' => $driver,
                'timestamp' => now()->toISOString()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status' => 'error',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Broadcast custom event
     * POST /api/v1/websocket/broadcast
     */
    public function broadcastCustomEvent(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'channel' => 'required|string',
            'event' => 'required|string',
            'data' => 'required|array'
        ]);

        try {
            // Log the custom broadcast event
            Log::info('Custom Broadcast Event', [
                'channel' => $validated['channel'],
                'event' => $validated['event'],
                'data' => $validated['data']
            ]);

            // For custom events, you would typically use Pusher or Redis directly
            // This is a placeholder for custom broadcasting logic
            
            return response()->json([
                'success' => true,
                'message' => 'Custom event logged successfully (implement broadcasting as needed)',
                'channel' => $validated['channel'],
                'event' => $validated['event']
            ]);

        } catch (\Exception $e) {
            Log::error('Custom Broadcast Error', [
                'channel' => $validated['channel'],
                'event' => $validated['event'],
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to broadcast custom event',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get list of active channels (for monitoring)
     * GET /api/v1/websocket/channels
     */
    public function getActiveChannels(Request $request): JsonResponse
    {
        // This would require integration with your WebSocket server
        // For now, return available channel types
        $channelTypes = [
            'agents' => 'Global agents channel',
            'agent.{id}' => 'Individual agent channel',
            'queues' => 'Global queues channel',
            'queue.{id}' => 'Individual queue channel',
            'tickets' => 'Global tickets channel',
            'conversation.{id}' => 'Individual conversation channel',
            'supervisor.{id}' => 'Supervisor monitoring channel'
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'channel_types' => $channelTypes,
                'broadcast_driver' => config('broadcasting.default')
            ]
        ]);
    }
}
