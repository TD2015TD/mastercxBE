<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Events\AgentStateChanged;
use App\Events\ConversationUpdated;
use App\Events\QueueStateChanged;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * MitelController - Integration with MiContact Center Web SDK
 * 
 * Handles telephony operations including:
 * - Agent state management (login, logout, busy, available)
 * - Conversation handling (accept, transfer, hold, end)
 * - Open Media interactions (email, chat, SMS)
 * - Real-time queue monitoring
 */
class MitelController extends Controller
{
    private string $mitelBaseUrl;
    private string $mitelToken;

    public function __construct()
    {
        // Configure Mitel API credentials from environment
        $this->mitelBaseUrl = config('services.mitel.base_url', '');
        $this->mitelToken = config('services.mitel.token', '');
    }

    /**
     * Get current agent state
     * GET /api/v1/mitel/agents/{agentId}/state
     */
    public function getAgentState(Request $request, string $agentId): JsonResponse
    {
        try {
            $response = Http::withToken($this->mitelToken)
                ->get("{$this->mitelBaseUrl}/api/v1/agents/{$agentId}/state");

            if ($response->successful()) {
                return response()->json([
                    'success' => true,
                    'data' => $response->json()
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve agent state',
                'error' => $response->body()
            ], $response->status());

        } catch (\Exception $e) {
            Log::error('Mitel API Error - Get Agent State', [
                'agent_id' => $agentId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Internal server error',
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
            'state' => 'required|string|in:Available,Busy,DoNotDisturb,Logout',
            'reason' => 'nullable|string',
            'device_id' => 'nullable|string'
        ]);

        try {
            $response = Http::withToken($this->mitelToken)
                ->put("{$this->mitelBaseUrl}/api/v1/employees/{$agentId}/state", [
                    'State' => $this->mapPresenceState($validated['state']),
                    'Reason' => $validated['reason'] ?? null,
                    'DeviceId' => $validated['device_id'] ?? null
                ]);

            if ($response->successful()) {
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
                        'state' => $validated['state']
                    ]
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to update agent state',
                'error' => $response->body()
            ], $response->status());

        } catch (\Exception $e) {
            Log::error('Mitel API Error - Update Agent State', [
                'agent_id' => $agentId,
                'state' => $validated['state'],
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Internal server error',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get agent's active conversations
     * GET /api/v1/mitel/agents/{agentId}/conversations
     */
    public function getConversations(Request $request, string $agentId): JsonResponse
    {
        $type = $request->query('type', ''); // active, drafts, failed

        try {
            $response = Http::withToken($this->mitelToken)
                ->get("{$this->mitelBaseUrl}/api/v1/employees/{$agentId}/conversations", [
                    'type' => $type
                ]);

            if ($response->successful()) {
                return response()->json([
                    'success' => true,
                    'data' => $response->json()
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve conversations',
                'error' => $response->body()
            ], $response->status());

        } catch (\Exception $e) {
            Log::error('Mitel API Error - Get Conversations', [
                'agent_id' => $agentId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Internal server error',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update conversation (accept, hold, transfer, end)
     * PUT /api/v1/mitel/agents/{agentId}/conversations/{conversationId}
     */
    public function updateConversation(Request $request, string $agentId, string $conversationId): JsonResponse
    {
        $validated = $request->validate([
            'action' => 'required|string|in:Accept,Decline,Hold,RemoveHold,Transfer,End,Pick',
            'transfer_to' => 'required_if:action,Transfer|nullable|string',
            'account_code' => 'nullable|string'
        ]);

        try {
            $payload = [
                'conversationAction' => $validated['action']
            ];

            if ($validated['action'] === 'Transfer' && !empty($validated['transfer_to'])) {
                $payload['transferTo'] = $validated['transfer_to'];
            }

            if (!empty($validated['account_code'])) {
                $payload['accountCode'] = $validated['account_code'];
            }

            $response = Http::withToken($this->mitelToken)
                ->put("{$this->mitelBaseUrl}/api/v1/employees/{$agentId}/conversations/{$conversationId}", $payload);

            if ($response->successful()) {
                // Broadcast conversation update event
                broadcast(new ConversationUpdated([
                    'agent_id' => $agentId,
                    'conversation_id' => $conversationId,
                    'action' => $validated['action'],
                    'timestamp' => now()
                ]));

                return response()->json([
                    'success' => true,
                    'message' => 'Conversation updated successfully',
                    'data' => [
                        'conversation_id' => $conversationId,
                        'action' => $validated['action']
                    ]
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to update conversation',
                'error' => $response->body()
            ], $response->status());

        } catch (\Exception $e) {
            Log::error('Mitel API Error - Update Conversation', [
                'agent_id' => $agentId,
                'conversation_id' => $conversationId,
                'action' => $validated['action'],
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Internal server error',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Initiate outbound call or conversation
     * POST /api/v1/mitel/agents/{agentId}/conversations
     */
    public function createConversation(Request $request, string $agentId): JsonResponse
    {
        $validated = $request->validate([
            'media_type' => 'required|string|in:Voice,SMS,Email',
            'destination' => 'required|string',
            'account_code' => 'nullable|string',
            'subject' => 'required_if:media_type,Email|nullable|string',
            'body' => 'required_if:media_type,Email,SMS|nullable|string'
        ]);

        try {
            $payload = [
                'mediaType' => $validated['media_type'],
                'destination' => $validated['destination']
            ];

            if (!empty($validated['account_code'])) {
                $payload['accountCode'] = $validated['account_code'];
            }

            if ($validated['media_type'] === 'Email') {
                $payload['subject'] = $validated['subject'] ?? '';
                $payload['body'] = $validated['body'] ?? '';
            }

            if ($validated['media_type'] === 'SMS') {
                $payload['message'] = $validated['body'] ?? '';
            }

            $response = Http::withToken($this->mitelToken)
                ->post("{$this->mitelBaseUrl}/api/v1/employees/{$agentId}/conversations", $payload);

            if ($response->successful()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Conversation initiated successfully',
                    'data' => $response->json()
                ], 201);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to create conversation',
                'error' => $response->body()
            ], $response->status());

        } catch (\Exception $e) {
            Log::error('Mitel API Error - Create Conversation', [
                'agent_id' => $agentId,
                'media_type' => $validated['media_type'],
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Internal server error',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get queue information with real-time statistics
     * GET /api/v1/mitel/queues/{queueId}
     */
    public function getQueueState(Request $request, string $queueId): JsonResponse
    {
        try {
            $response = Http::withToken($this->mitelToken)
                ->get("{$this->mitelBaseUrl}/api/v1/queues/{$queueId}/state");

            if ($response->successful()) {
                return response()->json([
                    'success' => true,
                    'data' => $response->json()
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve queue state',
                'error' => $response->body()
            ], $response->status());

        } catch (\Exception $e) {
            Log::error('Mitel API Error - Get Queue State', [
                'queue_id' => $queueId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Internal server error',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get list of available queues for agent
     * GET /api/v1/mitel/agents/{agentId}/queues
     */
    public function getAgentQueues(Request $request, string $agentId): JsonResponse
    {
        try {
            $response = Http::withToken($this->mitelToken)
                ->get("{$this->mitelBaseUrl}/api/v1/employees/{$agentId}/queues");

            if ($response->successful()) {
                return response()->json([
                    'success' => true,
                    'data' => $response->json()
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve agent queues',
                'error' => $response->body()
            ], $response->status());

        } catch (\Exception $e) {
            Log::error('Mitel API Error - Get Agent Queues', [
                'agent_id' => $agentId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Internal server error',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create OpenMedia interaction (for custom channels)
     * POST /api/v1/mitel/openmedia
     */
    public function createOpenMedia(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'queue_id' => 'required|string',
            'priority' => 'nullable|integer|min:1|max:10',
            'subject' => 'nullable|string',
            'body' => 'nullable|string',
            'contact_name' => 'nullable|string',
            'contact_email' => 'nullable|email',
            'contact_phone' => 'nullable|string',
            'variable_data' => 'nullable|array'
        ]);

        try {
            $payload = [
                'queueId' => $validated['queue_id'],
                'priority' => $validated['priority'] ?? 5,
                'subject' => $validated['subject'] ?? '',
                'body' => $validated['body'] ?? ''
            ];

            // Add contact information to variable data
            $variableData = $validated['variable_data'] ?? [];
            if (!empty($validated['contact_name'])) {
                $variableData['Name'] = $validated['contact_name'];
            }
            if (!empty($validated['contact_email'])) {
                $variableData['Email'] = $validated['contact_email'];
            }
            if (!empty($validated['contact_phone'])) {
                $variableData['Phone'] = $validated['contact_phone'];
            }

            if (!empty($variableData)) {
                $payload['variableData'] = $variableData;
            }

            $response = Http::withToken($this->mitelToken)
                ->post("{$this->mitelBaseUrl}/api/v1/openmedia", $payload);

            if ($response->successful()) {
                return response()->json([
                    'success' => true,
                    'message' => 'OpenMedia interaction created successfully',
                    'data' => $response->json()
                ], 201);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to create OpenMedia interaction',
                'error' => $response->body()
            ], $response->status());

        } catch (\Exception $e) {
            Log::error('Mitel API Error - Create OpenMedia', [
                'queue_id' => $validated['queue_id'],
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Internal server error',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update OpenMedia interaction (complete or reroute)
     * PUT /api/v1/mitel/openmedia/{interactionId}
     */
    public function updateOpenMedia(Request $request, string $interactionId): JsonResponse
    {
        $validated = $request->validate([
            'action' => 'required|string|in:Complete,Reroute',
            'queue_id' => 'required_if:action,Reroute|nullable|string'
        ]);

        try {
            $payload = [
                'action' => $validated['action']
            ];

            if ($validated['action'] === 'Reroute' && !empty($validated['queue_id'])) {
                $payload['queueId'] = $validated['queue_id'];
            }

            $response = Http::withToken($this->mitelToken)
                ->put("{$this->mitelBaseUrl}/api/v1/openmedia/{$interactionId}", $payload);

            if ($response->successful()) {
                return response()->json([
                    'success' => true,
                    'message' => 'OpenMedia interaction updated successfully'
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to update OpenMedia interaction',
                'error' => $response->body()
            ], $response->status());

        } catch (\Exception $e) {
            Log::error('Mitel API Error - Update OpenMedia', [
                'interaction_id' => $interactionId,
                'action' => $validated['action'],
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Internal server error',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get directory entries (contacts)
     * GET /api/v1/mitel/directory
     */
    public function getDirectory(Request $request): JsonResponse
    {
        $search = $request->query('search', '');

        try {
            $queryParams = [];
            if (!empty($search)) {
                $queryParams['$filter'] = "contains(Name,'$search') or contains(Email,'$search')";
            }

            $response = Http::withToken($this->mitelToken)
                ->get("{$this->mitelBaseUrl}/api/v1/directory", $queryParams);

            if ($response->successful()) {
                return response()->json([
                    'success' => true,
                    'data' => $response->json()
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve directory',
                'error' => $response->body()
            ], $response->status());

        } catch (\Exception $e) {
            Log::error('Mitel API Error - Get Directory', [
                'search' => $search,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Internal server error',
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

    /**
     * Map presence state to Mitel API format
     */
    private function mapPresenceState(string $state): int
    {
        $stateMap = [
            'Available' => 1,
            'Busy' => 2,
            'DoNotDisturb' => 3,
            'Logout' => 4
        ];

        return $stateMap[$state] ?? 1;
    }
}
