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
     * Blind transfer call - immediately transfers without consultation
     */
    #[OA\Post(
        path: "/api/v1/mitel/agents/{agentId}/calls/{callId}/transfer/blind",
        summary: "Blind transfer call to another agent or number",
        tags: ["Mitel - Calls"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["destination"],
                properties: [
                    new OA\Property(property: "destination", type: "string", example: "+60198765432", description: "Destination extension or phone number"),
                    new OA\Property(property: "ticket_id", type: "integer", example: 123, description: "Optional ticket ID to transfer with the call")
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
                description: "Call ID / Conversation ID",
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
                        new OA\Property(property: "message", type: "string", example: "Call and ticket transferred successfully")
                    ]
                )
            ),
            new OA\Response(response: 422, description: "Validation error"),
            new OA\Response(response: 500, description: "Server error")
        ]
    )]
    public function blindTransferCall(Request $request, string $agentId, string $callId): JsonResponse
    {
        $validated = $request->validate([
            'destination' => 'required|string',
            'ticket_id' => 'nullable|integer|exists:tickets,id',
            'destination_agent_id' => 'nullable|integer|exists:users,id'
        ]);

        try {
            // Perform blind transfer
            $data = $this->mitelService->blindTransfer('me', $callId, $validated['destination']);

            // If ticket_id is provided, transfer the ticket as well
            if (isset($validated['ticket_id']) && isset($validated['destination_agent_id'])) {
                $ticket = \App\Models\Ticket::find($validated['ticket_id']);
                if ($ticket) {
                    $ticket->assigned_to = $validated['destination_agent_id'];
                    $ticket->save();

                    // Log ticket transfer
                    \App\Models\TicketHistory::create([
                        'ticket_id' => $ticket->id,
                        'action' => 'transferred',
                        'changed_by' => auth()->id() ?? $agentId,
                        'old_value' => $ticket->getOriginal('assigned_to'),
                        'new_value' => $validated['destination_agent_id'],
                        'notes' => 'Ticket transferred during blind call transfer to ' . $validated['destination']
                    ]);

                    Log::info('Ticket transferred with call', [
                        'ticket_id' => $validated['ticket_id'],
                        'from_agent' => $agentId,
                        'to_agent' => $validated['destination_agent_id']
                    ]);
                }
            }

            // Broadcast conversation update event
            broadcast(new ConversationUpdated([
                'agent_id' => $agentId,
                'call_id' => $callId,
                'action' => 'blind_transferred',
                'destination' => $validated['destination'],
                'ticket_id' => $validated['ticket_id'] ?? null,
                'timestamp' => now()
            ]));

            $message = isset($validated['ticket_id'])
                ? 'Call and ticket transferred successfully'
                : 'Call transferred successfully';

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => $data
            ]);

        } catch (\Exception $e) {
            Log::error('Error in blind transfer', [
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
     * Initiate consultation transfer - creates consultation call for discussion before transfer
     */
    #[OA\Post(
        path: "/api/v1/mitel/agents/{agentId}/calls/{callId}/transfer/consult/initiate",
        summary: "Initiate consultation transfer (talk before transferring)",
        tags: ["Mitel - Calls"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["destination"],
                properties: [
                    new OA\Property(property: "destination", type: "string", example: "+60198765432", description: "Destination extension or phone number")
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
                description: "Call ID / Conversation ID",
                schema: new OA\Schema(type: "string")
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Consultation call initiated",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "message", type: "string", example: "Consultation call initiated")
                    ]
                )
            ),
            new OA\Response(response: 422, description: "Validation error"),
            new OA\Response(response: 500, description: "Server error")
        ]
    )]
    public function initiateConsultTransfer(Request $request, string $agentId, string $callId): JsonResponse
    {
        $validated = $request->validate([
            'destination' => 'required|string',
        ]);

        try {
            $data = $this->mitelService->initiateConsultTransfer('me', $callId, $validated['destination']);

            broadcast(new ConversationUpdated([
                'agent_id' => $agentId,
                'call_id' => $callId,
                'action' => 'consult_initiated',
                'destination' => $validated['destination'],
                'timestamp' => now()
            ]));

            return response()->json([
                'success' => true,
                'message' => 'Consultation call initiated',
                'data' => $data
            ]);

        } catch (\Exception $e) {
            Log::error('Error initiating consultation transfer', [
                'agent_id' => $agentId,
                'call_id' => $callId,
                'destination' => $validated['destination'],
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to initiate consultation transfer',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Complete consultation transfer - completes the transfer after consultation
     */
    #[OA\Post(
        path: "/api/v1/mitel/agents/{agentId}/calls/{callId}/transfer/consult/complete",
        summary: "Complete consultation transfer",
        tags: ["Mitel - Calls"],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "ticket_id", type: "integer", example: 123, description: "Optional ticket ID to transfer with the call"),
                    new OA\Property(property: "destination_agent_id", type: "integer", example: 456, description: "Destination agent user ID for ticket transfer")
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
                description: "Call ID / Conversation ID",
                schema: new OA\Schema(type: "string")
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Transfer completed successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "message", type: "string", example: "Call and ticket transferred successfully")
                    ]
                )
            ),
            new OA\Response(response: 500, description: "Server error")
        ]
    )]
    public function completeConsultTransfer(Request $request, string $agentId, string $callId): JsonResponse
    {
        $validated = $request->validate([
            'ticket_id' => 'nullable|integer|exists:tickets,id',
            'destination_agent_id' => 'nullable|integer|exists:users,id'
        ]);

        try {
            $data = $this->mitelService->completeConsultTransfer('me', $callId);

            // If ticket_id is provided, transfer the ticket as well
            if (isset($validated['ticket_id']) && isset($validated['destination_agent_id'])) {
                $ticket = \App\Models\Ticket::find($validated['ticket_id']);
                if ($ticket) {
                    $ticket->assigned_to = $validated['destination_agent_id'];
                    $ticket->save();

                    // Log ticket transfer
                    \App\Models\TicketHistory::create([
                        'ticket_id' => $ticket->id,
                        'action' => 'transferred',
                        'changed_by' => auth()->id() ?? $agentId,
                        'old_value' => $ticket->getOriginal('assigned_to'),
                        'new_value' => $validated['destination_agent_id'],
                        'notes' => 'Ticket transferred during consultation call transfer'
                    ]);

                    Log::info('Ticket transferred with call', [
                        'ticket_id' => $validated['ticket_id'],
                        'from_agent' => $agentId,
                        'to_agent' => $validated['destination_agent_id']
                    ]);
                }
            }

            broadcast(new ConversationUpdated([
                'agent_id' => $agentId,
                'call_id' => $callId,
                'action' => 'consult_completed',
                'ticket_id' => $validated['ticket_id'] ?? null,
                'timestamp' => now()
            ]));

            $message = isset($validated['ticket_id'])
                ? 'Call and ticket transferred successfully'
                : 'Transfer completed successfully';

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => $data
            ]);

        } catch (\Exception $e) {
            Log::error('Error completing consultation transfer', [
                'agent_id' => $agentId,
                'call_id' => $callId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to complete transfer',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cancel consultation transfer - cancel the consultation and return to original call
     */
    #[OA\Post(
        path: "/api/v1/mitel/agents/{agentId}/calls/{callId}/transfer/consult/cancel",
        summary: "Cancel consultation transfer",
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
                description: "Call ID / Conversation ID",
                schema: new OA\Schema(type: "string")
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Consultation canceled successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "message", type: "string", example: "Consultation canceled")
                    ]
                )
            ),
            new OA\Response(response: 500, description: "Server error")
        ]
    )]
    public function cancelConsultTransfer(Request $request, string $agentId, string $callId): JsonResponse
    {
        try {
            $data = $this->mitelService->cancelConsultTransfer('me', $callId);

            broadcast(new ConversationUpdated([
                'agent_id' => $agentId,
                'call_id' => $callId,
                'action' => 'consult_canceled',
                'timestamp' => now()
            ]));

            return response()->json([
                'success' => true,
                'message' => 'Consultation canceled',
                'data' => $data
            ]);

        } catch (\Exception $e) {
            Log::error('Error canceling consultation transfer', [
                'agent_id' => $agentId,
                'call_id' => $callId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel consultation',
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

    /**
     * Get agent group presence
     * Returns agent groups with their presence states (Voice, OpenMedia, etc.)
     */
    #[OA\Get(
        path: "/api/v1/mitel/agents/{agentId}/agentgrouppresence",
        summary: "Get agent group presence information",
        tags: ["Mitel - Agents"],
        parameters: [
            new OA\Parameter(
                name: "agentId",
                in: "path",
                required: true,
                description: "Agent ID (use 'me' for current agent)",
                schema: new OA\Schema(type: "string")
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Agent group presence retrieved successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(
                            property: "data",
                            type: "object",
                            properties: [
                                new OA\Property(
                                    property: "agentGroups",
                                    type: "array",
                                    items: new OA\Items(
                                        properties: [
                                            new OA\Property(property: "agentGroupId", type: "string"),
                                            new OA\Property(property: "agentGroupName", type: "string"),
                                            new OA\Property(property: "presenceState", type: "string"),
                                            new OA\Property(property: "mediaType", type: "string", example: "Voice")
                                        ]
                                    )
                                )
                            ]
                        )
                    ]
                )
            ),
            new OA\Response(response: 500, description: "Server error")
        ]
    )]
    public function getAgentGroupPresence(Request $request, string $agentId): JsonResponse
    {
        try {
            $data = $this->mitelService->getAgentGroupPresence($agentId);

            return response()->json([
                'success' => true,
                'data' => $data
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting agent group presence', [
                'agent_id' => $agentId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve agent group presence',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get integrated softphone credentials
     * Returns WebRTC credentials for connecting to Mitel Border Gateway
     */
    #[OA\Get(
        path: "/api/v1/mitel/agents/{agentId}/integratedsoftphone",
        summary: "Get integrated softphone credentials for WebRTC",
        tags: ["Mitel - Agents"],
        parameters: [
            new OA\Parameter(
                name: "agentId",
                in: "path",
                required: true,
                description: "Agent ID (Voice agent ID from agent group presence)",
                schema: new OA\Schema(type: "string")
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Softphone credentials retrieved successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(
                            property: "data",
                            type: "object",
                            properties: [
                                new OA\Property(property: "gatewayMediaServerType", type: "string", example: "MitelBorderGateway"),
                                new OA\Property(property: "gatewayMediaServerDomain", type: "string", example: "mbg.telediant.com.my"),
                                new OA\Property(property: "gatewayMediaServerPort", type: "string", example: "5063"),
                                new OA\Property(property: "gatewayMediaServerConsumerId", type: "string", example: "MiccWeb"),
                                new OA\Property(property: "gatewayMediaServerSharedSecret", type: "string"),
                                new OA\Property(property: "integratedSoftphonePassword", type: "string"),
                                new OA\Property(property: "jwtIssuer", type: "string")
                            ]
                        )
                    ]
                )
            ),
            new OA\Response(response: 500, description: "Server error")
        ]
    )]
    public function getIntegratedSoftphoneCredentials(Request $request, string $agentId): JsonResponse
    {
        try {
            $data = $this->mitelService->getIntegratedSoftphoneCredentials($agentId);

            return response()->json([
                'success' => true,
                'data' => $data
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting integrated softphone credentials', [
                'agent_id' => $agentId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve integrated softphone credentials',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get busy reason codes
     * Returns available reason codes when agent changes status to busy
     */
    #[OA\Get(
        path: "/api/v1/mitel/agents/{agentId}/busyreasoncodes",
        summary: "Get busy reason codes for agent",
        tags: ["Mitel - Agents"],
        parameters: [
            new OA\Parameter(
                name: "agentId",
                in: "path",
                required: true,
                description: "Agent ID (use 'me' for current agent)",
                schema: new OA\Schema(type: "string")
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Busy reason codes retrieved successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(
                            property: "data",
                            type: "object",
                            properties: [
                                new OA\Property(property: "count", type: "integer", example: 2),
                                new OA\Property(
                                    property: "_embedded",
                                    type: "object",
                                    properties: [
                                        new OA\Property(
                                            property: "items",
                                            type: "array",
                                            items: new OA\Items(
                                                properties: [
                                                    new OA\Property(property: "id", type: "string"),
                                                    new OA\Property(property: "name", type: "string", example: "No Make Busy Code"),
                                                    new OA\Property(property: "reporting", type: "string"),
                                                    new OA\Property(property: "type", type: "string", example: "Busy")
                                                ]
                                            )
                                        )
                                    ]
                                )
                            ]
                        )
                    ]
                )
            ),
            new OA\Response(response: 500, description: "Server error")
        ]
    )]
    public function getBusyReasonCodes(Request $request, string $agentId): JsonResponse
    {
        try {
            $data = $this->mitelService->getBusyReasonCodes($agentId);

            return response()->json([
                'success' => true,
                'data' => $data
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting busy reason codes', [
                'agent_id' => $agentId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve busy reason codes',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Search directory for employees/agents
     * Used for call transfer destination selection
     */
    #[OA\Get(
        path: "/api/v1/mitel/directory/search",
        summary: "Search directory for employees/agents",
        tags: ["Mitel - Directory"],
        parameters: [
            new OA\Parameter(
                name: "q",
                in: "query",
                required: false,
                description: "Search query (searches by name or extension)",
                schema: new OA\Schema(type: "string")
            ),
            new OA\Parameter(
                name: "voiceOnly",
                in: "query",
                required: false,
                description: "Return only voice-enabled entries",
                schema: new OA\Schema(type: "boolean", default: true)
            ),
            new OA\Parameter(
                name: "skip",
                in: "query",
                required: false,
                description: "Number of entries to skip (pagination)",
                schema: new OA\Schema(type: "integer", default: 0)
            ),
            new OA\Parameter(
                name: "top",
                in: "query",
                required: false,
                description: "Number of entries to return",
                schema: new OA\Schema(type: "integer", default: 25)
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Directory search results",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(
                            property: "data",
                            type: "object",
                            properties: [
                                new OA\Property(property: "count", type: "integer"),
                                new OA\Property(
                                    property: "_embedded",
                                    type: "object",
                                    properties: [
                                        new OA\Property(
                                            property: "items",
                                            type: "array",
                                            items: new OA\Items(
                                                properties: [
                                                    new OA\Property(property: "id", type: "string"),
                                                    new OA\Property(property: "name", type: "string", example: "Kastam1 Kastam1"),
                                                    new OA\Property(property: "email", type: "string"),
                                                    new OA\Property(property: "phone", type: "string", example: "8001"),
                                                    new OA\Property(property: "type", type: "string", example: "Employee"),
                                                    new OA\Property(property: "presence", type: "object")
                                                ]
                                            )
                                        )
                                    ]
                                )
                            ]
                        )
                    ]
                )
            ),
            new OA\Response(response: 500, description: "Server error")
        ]
    )]
    public function searchDirectory(Request $request): JsonResponse
    {
        try {
            $searchQuery = $request->query('q', '');
            $voiceOnly = filter_var($request->query('voiceOnly', true), FILTER_VALIDATE_BOOLEAN);
            $skip = (int) $request->query('skip', 0);
            $top = (int) $request->query('top', 25);
            $searchType = $request->query('searchType', 'text'); // 'text' or 'id'

            $data = $this->mitelService->searchDirectory($searchQuery, $voiceOnly, $skip, $top, $searchType);

            return response()->json([
                'success' => true,
                'data' => $data
            ]);

        } catch (\Exception $e) {
            Log::error('Error searching directory', [
                'query' => $request->query('q'),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to search directory',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Make an outbound call
     * Creates a new conversation to initiate an outbound call
     */
    #[OA\Post(
        path: "/api/v1/mitel/employees/{employeeId}/conversations",
        summary: "Make an outbound call",
        tags: ["Mitel - Calls"],
        parameters: [
            new OA\Parameter(
                name: "employeeId",
                in: "path",
                required: true,
                description: "Employee ID (use 'me' for current employee)",
                schema: new OA\Schema(type: "string")
            ),
            new OA\Parameter(
                name: "dialAsIs",
                in: "query",
                required: false,
                description: "Whether to dial the number as-is without modifications",
                schema: new OA\Schema(type: "boolean", default: false)
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["to", "type"],
                properties: [
                    new OA\Property(property: "to", type: "string", description: "Destination agent ID or phone number"),
                    new OA\Property(property: "from", type: "string", description: "Caller ID (optional)", default: ""),
                    new OA\Property(property: "type", type: "string", description: "Call type", default: "Voice"),
                    new OA\Property(
                        property: "lightDirectoryEntryModels",
                        type: "array",
                        description: "Directory entry models for the contact",
                        items: new OA\Items(
                            properties: [
                                new OA\Property(property: "id", type: "string"),
                                new OA\Property(property: "name", type: "string"),
                                new OA\Property(property: "email", type: "string"),
                                new OA\Property(property: "phone", type: "string"),
                                new OA\Property(property: "agentId", type: "string", nullable: true),
                                new OA\Property(property: "type", type: "string")
                            ]
                        )
                    )
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Call initiated successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "data", type: "object")
                    ]
                )
            ),
            new OA\Response(response: 500, description: "Server error")
        ]
    )]
    public function makeOutboundCall(Request $request, string $employeeId): JsonResponse
    {
        try {
            $validated = $request->validate([
                'to' => 'required|string',
                'from' => 'nullable|string',
                'type' => 'nullable|string',
                'lightDirectoryEntryModels' => 'nullable|array',
                'lightDirectoryEntryModels.*.id' => 'nullable|string',
                'lightDirectoryEntryModels.*.name' => 'nullable|string',
                'lightDirectoryEntryModels.*.email' => 'nullable|string',
                'lightDirectoryEntryModels.*.phone' => 'nullable|string',
                'lightDirectoryEntryModels.*.agentId' => 'nullable|string',
                'lightDirectoryEntryModels.*.type' => 'nullable|string',
            ]);

            $dialAsIs = filter_var($request->query('dialAsIs', false), FILTER_VALIDATE_BOOLEAN);

            $data = $this->mitelService->makeOutboundCall(
                $employeeId,
                $validated['to'],
                $validated['from'] ?? '',
                $validated['type'] ?? 'Voice',
                $dialAsIs,
                $validated['lightDirectoryEntryModels'] ?? []
            );

            return response()->json([
                'success' => true,
                'data' => $data
            ]);

        } catch (\Exception $e) {
            Log::error('Error making outbound call', [
                'employee_id' => $employeeId,
                'to' => $request->input('to'),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to make outbound call',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
