<?php

namespace App\Http\Controllers\Api\Mitel;

use App\Http\Controllers\Controller;
use App\Services\MitelService;
use App\Events\AgentStateChanged;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use OpenApi\Attributes as OA;

/**
 * AgentController - Agent state and statistics management
 * 
 * Handles agent-specific operations including state management
 * (Available, Busy, Away, etc.) and performance statistics.
 */
#[OA\Tag(
    name: "Mitel - Agents",
    description: "Agent state management and statistics for Mitel telephony system"
)]
class AgentController extends Controller
{
    protected MitelService $mitelService;

    public function __construct(MitelService $mitelService)
    {
        $this->mitelService = $mitelService;
    }

    /**
     * Get list of all Mitel agents
     */
    #[OA\Get(
        path: "/api/v1/mitel/agents",
        summary: "Get list of all Mitel agents",
        tags: ["Mitel - Agents"],
        parameters: [
            new OA\Parameter(
                name: "filter",
                in: "query",
                required: false,
                description: "OData filter expression (e.g., state eq 'Available')",
                schema: new OA\Schema(type: "string")
            ),
            new OA\Parameter(
                name: "top",
                in: "query",
                required: false,
                description: "Maximum number of results to return",
                schema: new OA\Schema(type: "integer")
            ),
            new OA\Parameter(
                name: "skip",
                in: "query",
                required: false,
                description: "Number of results to skip",
                schema: new OA\Schema(type: "integer")
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Agents retrieved successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(
                            property: "data",
                            type: "array",
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: "id", type: "string", example: "1234"),
                                    new OA\Property(property: "name", type: "string", example: "John Doe"),
                                    new OA\Property(property: "extension", type: "string", example: "5001"),
                                    new OA\Property(property: "state", type: "string", example: "Available"),
                                    new OA\Property(
                                        property: "queues",
                                        type: "array",
                                        items: new OA\Items(type: "string")
                                    ),
                                    new OA\Property(property: "queue_count", type: "integer", example: 2)
                                ],
                                type: "object"
                            )
                        )
                    ]
                )
            ),
            new OA\Response(response: 500, description: "Server error")
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        try {
            // Build OData query parameters
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
            
            // Get agents from Mitel service
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
     */
    #[OA\Get(
        path: "/api/v1/mitel/agents/{agentId}/state",
        summary: "Get agent current state",
        tags: ["Mitel - Agents"],
        parameters: [
            new OA\Parameter(
                name: "agentId",
                in: "path",
                required: true,
                description: "Agent ID",
                schema: new OA\Schema(type: "string")
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Agent state retrieved successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(
                            property: "data",
                            properties: [
                                new OA\Property(property: "agent_id", type: "string", example: "1234"),
                                new OA\Property(property: "state", type: "string", example: "Available"),
                                new OA\Property(property: "logged_in", type: "boolean", example: true)
                            ],
                            type: "object"
                        )
                    ]
                )
            ),
            new OA\Response(response: 500, description: "Server error")
        ]
    )]
    public function getState(Request $request, string $agentId): JsonResponse
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
     * Update agent state
     */
    #[OA\Put(
        path: "/api/v1/mitel/agents/{agentId}/state",
        summary: "Update agent state",
        tags: ["Mitel - Agents"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["state"],
                properties: [
                    new OA\Property(
                        property: "state",
                        type: "string",
                        enum: ["Available", "Busy", "DoNotDisturb", "Away", "Logout"],
                        example: "Available"
                    ),
                    new OA\Property(property: "reason", type: "string", example: "Break")
                ]
            )
        ),
        parameters: [
            new OA\Parameter(
                name: "agentId",
                in: "path",
                required: true,
                description: "Agent ID",
                schema: new OA\Schema(type: "string")
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Agent state updated successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "message", type: "string", example: "Agent state updated successfully"),
                        new OA\Property(
                            property: "data",
                            properties: [
                                new OA\Property(property: "agent_id", type: "string", example: "1234"),
                                new OA\Property(property: "state", type: "string", example: "Available")
                            ],
                            type: "object"
                        )
                    ]
                )
            ),
            new OA\Response(response: 422, description: "Validation error"),
            new OA\Response(response: 500, description: "Server error")
        ]
    )]
    public function updateState(Request $request, string $agentId): JsonResponse
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
     */
    #[OA\Get(
        path: "/api/v1/mitel/agents/{agentId}/statistics",
        summary: "Get agent performance statistics",
        tags: ["Mitel - Agents"],
        parameters: [
            new OA\Parameter(
                name: "agentId",
                in: "path",
                required: true,
                description: "Agent ID",
                schema: new OA\Schema(type: "string")
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Statistics retrieved successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(
                            property: "data",
                            properties: [
                                new OA\Property(property: "total_calls", type: "integer", example: 45),
                                new OA\Property(property: "answered_calls", type: "integer", example: 42),
                                new OA\Property(property: "average_talk_time", type: "string", example: "00:05:23")
                            ],
                            type: "object"
                        )
                    ]
                )
            ),
            new OA\Response(response: 500, description: "Server error")
        ]
    )]
    public function getStatistics(Request $request, string $agentId): JsonResponse
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
}
