<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MitelService;
use App\Events\AgentStateChanged;
use App\Events\ConversationUpdated;
use App\Events\QueueStateChanged;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * MitelController - Integration with MiContact Center Web SDK
 * 
 * This controller acts as an intermediary between the frontend and Mitel API.
 * All Mitel API calls are handled through the MitelService layer.
 * 
 * Handles telephony operations including:
 * - Agent state management (login, logout, busy, available)
 * - Conversation handling (accept, transfer, hold, end)
 * - Open Media interactions (email, chat, SMS)
 * - Real-time queue monitoring
 */
class MitelController extends Controller
{
    protected MitelService $mitelService;

    public function __construct(MitelService $mitelService)
    {
        $this->mitelService = $mitelService;
    }

    /**
     * Get list of all Mitel agents
     * GET /api/v1/mitel/agents
     */
    public function getAgents(Request $request): JsonResponse
    {
        try {
            // Get OData query parameters for filtering
            $odataParams = [];
            
            if ($request->has('filter')) {
                $odataParams['$filter'] = $request->query('filter');
            }
            
            if ($request->has('top')) {
                $odataParams['$top'] = $request->query('top');
            }
            
            if ($request->has('skip')) {
                $odataParams['$skip'] = $request->query('skip');
            }
            
            $data = $this->mitelService->getAgents($odataParams);

            // Transform data for frontend
            $agents = [];
            if (isset($data['value']) && is_array($data['value'])) {
                foreach ($data['value'] as $agent) {
                    $agents[] = [
                        'id' => $agent['id'] ?? null,
                        'name' => $agent['name'] ?? null,
                        'extension' => $agent['extension'] ?? null,
                        'state' => $agent['state'] ?? null,
                        'queues' => $agent['queues'] ?? [],
                        'queue_count' => isset($agent['queues']) ? count($agent['queues']) : 0,
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'data' => $agents
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting Mitel agents', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve Mitel agents',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get current agent state
     * GET /api/v1/mitel/agents/{agentId}/state
     */
    public function getAgentState(Request $request, string $agentId): JsonResponse
    {
        try {
            $data = $this->mitelService->getAgentStatus($agentId);

            return response()->json([
                'success' => true,
                'data' => $data
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting agent state', [
                'agent_id' => $agentId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve agent state',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update agent state (login, logout, busy, available)
     * PUT /api/v1/mitel/agents/{agentId}/state
     */
    public function updateAgentState(Request $request, string $agentId): JsonResponse
    {
        $validated = $request->validate([
            'state' => 'required|string|in:Available,Busy,DoNotDisturb,Away,Logout',
            'reason' => 'nullable|string'
        ]);

        try {
            $data = $this->mitelService->setAgentStatus($agentId, $validated['state']);

            // Broadcast agent state change event
            broadcast(new AgentStateChanged([
                'agent_id' => $agentId,
                'state' => $validated['state'],
                'reason' => $validated['reason'] ?? null,
                'timestamp' => now()
            ]));

            return response()->json([
                'success' => true,
                'message' => 'Agent state updated successfully',
                'data' => [
                    'agent_id' => $agentId,
                    'state' => $validated['state'],
                    'mitel_response' => $data
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error updating agent state', [
                'agent_id' => $agentId,
                'state' => $validated['state'],
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update agent state',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get agent statistics
     * GET /api/v1/mitel/agents/{agentId}/statistics
     */
    public function getAgentStatistics(Request $request, string $agentId): JsonResponse
    {
        try {
            $data = $this->mitelService->getAgentStats($agentId);

            return response()->json([
                'success' => true,
                'data' => $data
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting agent statistics', [
                'agent_id' => $agentId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve agent statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get agent's active calls/conversations
     * GET /api/v1/mitel/agents/{agentId}/calls
     */
    public function getActiveCalls(Request $request, string $agentId): JsonResponse
    {
        try {
            $data = $this->mitelService->getActiveCalls($agentId);

            return response()->json([
                'success' => true,
                'data' => $data
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting active calls', [
                'agent_id' => $agentId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve active calls',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Initiate outbound call
     * POST /api/v1/mitel/agents/{agentId}/call
     */
    public function makeCall(Request $request, string $agentId): JsonResponse
    {
        $validated = $request->validate([
            'phone_number' => 'required|string|regex:/^[0-9+\-() ]+$/',
        ]);

        try {
            $data = $this->mitelService->makeCall($agentId, $validated['phone_number']);

            return response()->json([
                'success' => true,
                'message' => 'Call initiated successfully',
                'data' => $data
            ], 201);

        } catch (\Exception $e) {
            Log::error('Error making call', [
                'agent_id' => $agentId,
                'phone_number' => $validated['phone_number'],
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to initiate call',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Answer incoming call
     * POST /api/v1/mitel/agents/{agentId}/calls/{callId}/answer
     */
    public function answerCall(Request $request, string $agentId, string $callId): JsonResponse
    {
        try {
            $data = $this->mitelService->answerCall($agentId, $callId);

            // Broadcast conversation update event
            broadcast(new ConversationUpdated([
                'agent_id' => $agentId,
                'call_id' => $callId,
                'action' => 'answered',
                'timestamp' => now()
            ]));

            return response()->json([
                'success' => true,
                'message' => 'Call answered successfully',
                'data' => $data
            ]);

        } catch (\Exception $e) {
            Log::error('Error answering call', [
                'agent_id' => $agentId,
                'call_id' => $callId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to answer call',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * End call
     * POST /api/v1/mitel/agents/{agentId}/calls/{callId}/end
     */
    public function endCall(Request $request, string $agentId, string $callId): JsonResponse
    {
        try {
            $data = $this->mitelService->endCall($agentId, $callId);

            // Broadcast conversation update event
            broadcast(new ConversationUpdated([
                'agent_id' => $agentId,
                'call_id' => $callId,
                'action' => 'ended',
                'timestamp' => now()
            ]));

            return response()->json([
                'success' => true,
                'message' => 'Call ended successfully',
                'data' => $data
            ]);

        } catch (\Exception $e) {
            Log::error('Error ending call', [
                'agent_id' => $agentId,
                'call_id' => $callId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to end call',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Hold call
     * POST /api/v1/mitel/agents/{agentId}/calls/{callId}/hold
     */
    public function holdCall(Request $request, string $agentId, string $callId): JsonResponse
    {
        try {
            $data = $this->mitelService->holdCall($agentId, $callId);

            return response()->json([
                'success' => true,
                'message' => 'Call put on hold successfully',
                'data' => $data
            ]);

        } catch (\Exception $e) {
            Log::error('Error holding call', [
                'agent_id' => $agentId,
                'call_id' => $callId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to hold call',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Resume call from hold
     * POST /api/v1/mitel/agents/{agentId}/calls/{callId}/resume
     */
    public function resumeCall(Request $request, string $agentId, string $callId): JsonResponse
    {
        try {
            $data = $this->mitelService->resumeCall($agentId, $callId);

            return response()->json([
                'success' => true,
                'message' => 'Call resumed successfully',
                'data' => $data
            ]);

        } catch (\Exception $e) {
            Log::error('Error resuming call', [
                'agent_id' => $agentId,
                'call_id' => $callId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to resume call',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Transfer call to another agent or number
     * POST /api/v1/mitel/agents/{agentId}/calls/{callId}/transfer
     */
    public function transferCall(Request $request, string $agentId, string $callId): JsonResponse
    {
        $validated = $request->validate([
            'destination' => 'required|string',
        ]);

        try {
            $data = $this->mitelService->transferCall($agentId, $callId, $validated['destination']);

            // Broadcast conversation update event
            broadcast(new ConversationUpdated([
                'agent_id' => $agentId,
                'call_id' => $callId,
                'action' => 'transferred',
                'destination' => $validated['destination'],
                'timestamp' => now()
            ]));

            return response()->json([
                'success' => true,
                'message' => 'Call transferred successfully',
                'data' => $data
            ]);

        } catch (\Exception $e) {
            Log::error('Error transferring call', [
                'agent_id' => $agentId,
                'call_id' => $callId,
                'destination' => $validated['destination'],
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to transfer call',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get call history for agent
     * GET /api/v1/mitel/agents/{agentId}/history
     */
    public function getCallHistory(Request $request, string $agentId): JsonResponse
    {
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        try {
            $data = $this->mitelService->getCallHistory($agentId, $startDate, $endDate);

            return response()->json([
                'success' => true,
                'data' => $data
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting call history', [
                'agent_id' => $agentId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve call history',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get queue statistics
     * GET /api/v1/mitel/queues/{queueId}/statistics
     */
    public function getQueueStatistics(Request $request, string $queueId): JsonResponse
    {
        try {
            $data = $this->mitelService->getQueueStats($queueId);

            return response()->json([
                'success' => true,
                'data' => $data
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting queue statistics', [
                'queue_id' => $queueId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve queue statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all available queues
     * GET /api/v1/mitel/queues
     */
    public function getQueues(Request $request): JsonResponse
    {
        try {
            $data = $this->mitelService->getQueues();

            return response()->json([
                'success' => true,
                'data' => $data
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting queues', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve queues',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Webhook receiver for Mitel real-time events
     * POST /api/v1/mitel/webhook
     */
    public function webhook(Request $request): JsonResponse
    {
        try {
            $eventType = $request->input('eventType');
            $eventData = $request->input('data');

            Log::info('Mitel Webhook Received', [
                'event_type' => $eventType,
                'data' => $eventData
            ]);

            // Process different event types
            switch ($eventType) {
                case 'AgentStateChanged':
                    broadcast(new AgentStateChanged($eventData));
                    break;

                case 'ConversationUpdated':
                    broadcast(new ConversationUpdated($eventData));
                    break;

                case 'QueueStateChanged':
                    broadcast(new QueueStateChanged($eventData));
                    break;

                default:
                    Log::warning('Unknown Mitel event type', ['event_type' => $eventType]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Webhook processed successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Mitel Webhook Error', [
                'error' => $e->getMessage(),
                'request' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to process webhook',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
