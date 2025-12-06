<?php

namespace App\Http\Controllers\Api\Mitel;

use App\Http\Controllers\Controller;
use App\Services\MitelService;
use App\Events\ConversationUpdated;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use OpenApi\Attributes as OA;

/**
 * CallController - Telephony call operations
 * 
 * Handles all call-related operations including making outbound calls,
 * answering, holding, transferring, and ending calls.
 */
#[OA\Tag(
    name: "Mitel - Calls",
    description: "Telephony call management operations"
)]
class CallController extends Controller
{
    protected MitelService $mitelService;

    public function __construct(MitelService $mitelService)
    {
        $this->mitelService = $mitelService;
    }

    /**
     * Get active calls for agent
     */
    #[OA\Get(
        path: "/api/v1/mitel/agents/{agentId}/calls",
        summary: "Get agent's active calls",
        tags: ["Mitel - Calls"],
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
                description: "Active calls retrieved successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(
                            property: "data",
                            type: "array",
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: "call_id", type: "string", example: "call-123"),
                                    new OA\Property(property: "phone_number", type: "string", example: "+60123456789"),
                                    new OA\Property(property: "status", type: "string", example: "active")
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
     */
    #[OA\Post(
        path: "/api/v1/mitel/agents/{agentId}/calls",
        summary: "Make outbound call",
        tags: ["Mitel - Calls"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["phone_number"],
                properties: [
                    new OA\Property(property: "phone_number", type: "string", example: "+60123456789")
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
                response: 201,
                description: "Call initiated successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "message", type: "string", example: "Call initiated successfully"),
                        new OA\Property(
                            property: "data",
                            properties: [
                                new OA\Property(property: "call_id", type: "string", example: "call-123"),
                                new OA\Property(property: "status", type: "string", example: "connecting")
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
     */
    #[OA\Post(
        path: "/api/v1/mitel/agents/{agentId}/calls/{callId}/answer",
        summary: "Answer incoming call",
        tags: ["Mitel - Calls"],
        parameters: [
            new OA\Parameter(
                name: "agentId",
                in: "path",
                required: true,
                description: "Agent ID",
                schema: new OA\Schema(type: "string")
            ),
            new OA\Parameter(
                name: "callId",
                in: "path",
                required: true,
                description: "Call ID",
                schema: new OA\Schema(type: "string")
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Call answered successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "message", type: "string", example: "Call answered successfully")
                    ]
                )
            ),
            new OA\Response(response: 500, description: "Server error")
        ]
    )]
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
     * Hold call
     */
    #[OA\Post(
        path: "/api/v1/mitel/agents/{agentId}/calls/{callId}/hold",
        summary: "Put call on hold",
        tags: ["Mitel - Calls"],
        parameters: [
            new OA\Parameter(
                name: "agentId",
                in: "path",
                required: true,
                description: "Agent ID",
                schema: new OA\Schema(type: "string")
            ),
            new OA\Parameter(
                name: "callId",
                in: "path",
                required: true,
                description: "Call ID",
                schema: new OA\Schema(type: "string")
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Call put on hold successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "message", type: "string", example: "Call put on hold successfully")
                    ]
                )
            ),
            new OA\Response(response: 500, description: "Server error")
        ]
    )]
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
     */
    #[OA\Post(
        path: "/api/v1/mitel/agents/{agentId}/calls/{callId}/resume",
        summary: "Resume call from hold",
        tags: ["Mitel - Calls"],
        parameters: [
            new OA\Parameter(
                name: "agentId",
                in: "path",
                required: true,
                description: "Agent ID",
                schema: new OA\Schema(type: "string")
            ),
            new OA\Parameter(
                name: "callId",
                in: "path",
                required: true,
                description: "Call ID",
                schema: new OA\Schema(type: "string")
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Call resumed successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "message", type: "string", example: "Call resumed successfully")
                    ]
                )
            ),
            new OA\Response(response: 500, description: "Server error")
        ]
    )]
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
     * Transfer call
     */
    #[OA\Post(
        path: "/api/v1/mitel/agents/{agentId}/calls/{callId}/transfer",
        summary: "Transfer call to another agent or number",
        tags: ["Mitel - Calls"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["destination"],
                properties: [
                    new OA\Property(property: "destination", type: "string", example: "+60198765432")
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
            ),
            new OA\Parameter(
                name: "callId",
                in: "path",
                required: true,
                description: "Call ID",
                schema: new OA\Schema(type: "string")
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Call transferred successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "message", type: "string", example: "Call transferred successfully")
                    ]
                )
            ),
            new OA\Response(response: 422, description: "Validation error"),
            new OA\Response(response: 500, description: "Server error")
        ]
    )]
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
     * End call
     */
    #[OA\Post(
        path: "/api/v1/mitel/agents/{agentId}/calls/{callId}/end",
        summary: "End active call",
        tags: ["Mitel - Calls"],
        parameters: [
            new OA\Parameter(
                name: "agentId",
                in: "path",
                required: true,
                description: "Agent ID",
                schema: new OA\Schema(type: "string")
            ),
            new OA\Parameter(
                name: "callId",
                in: "path",
                required: true,
                description: "Call ID",
                schema: new OA\Schema(type: "string")
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Call ended successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "message", type: "string", example: "Call ended successfully")
                    ]
                )
            ),
            new OA\Response(response: 500, description: "Server error")
        ]
    )]
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
     * Get call history
     */
    #[OA\Get(
        path: "/api/v1/mitel/agents/{agentId}/calls/history",
        summary: "Get agent call history",
        tags: ["Mitel - Calls"],
        parameters: [
            new OA\Parameter(
                name: "agentId",
                in: "path",
                required: true,
                description: "Agent ID",
                schema: new OA\Schema(type: "string")
            ),
            new OA\Parameter(
                name: "start_date",
                in: "query",
                required: false,
                description: "Start date (YYYY-MM-DD)",
                schema: new OA\Schema(type: "string", format: "date")
            ),
            new OA\Parameter(
                name: "end_date",
                in: "query",
                required: false,
                description: "End date (YYYY-MM-DD)",
                schema: new OA\Schema(type: "string", format: "date")
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Call history retrieved successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(
                            property: "data",
                            type: "array",
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: "call_id", type: "string", example: "call-123"),
                                    new OA\Property(property: "phone_number", type: "string", example: "+60123456789"),
                                    new OA\Property(property: "duration", type: "string", example: "00:05:23"),
                                    new OA\Property(property: "timestamp", type: "string", format: "date-time")
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
}
