<?php

namespace App\Http\Controllers\Api\Mitel;

use App\Http\Controllers\Controller;
use App\Events\AgentStateChanged;
use App\Events\ConversationUpdated;
use App\Events\MitelInteractionUpdated;
use App\Events\QueueStateChanged;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use OpenApi\Attributes as OA;

/**
 * WebhookController - Mitel real-time event webhooks
 * 
 * Receives and processes real-time events from Mitel telephony system
 * including agent state changes, conversation updates, and queue state changes.
 * These events are broadcast to connected WebSocket clients for real-time UI updates.
 */
#[OA\Tag(
    name: "Mitel - Webhooks",
    description: "Webhook endpoints for receiving real-time events from Mitel system"
)]
class WebhookController extends Controller
{
    /**
     * Receive Mitel webhook events
     * 
     * This endpoint receives real-time events from the Mitel system and broadcasts
     * them to connected clients via WebSockets for real-time UI updates.
     * 
     * Supported event types:
     * - AgentStateChanged: When an agent changes their availability status
     * - ConversationUpdated: When a call/conversation state changes
     * - QueueStateChanged: When queue statistics or state changes
     */
    #[OA\Post(
        path: "/api/v1/mitel/webhook",
        summary: "Receive Mitel real-time events (Webhook)",
        description: "Webhook endpoint for Mitel to send real-time events. Events are broadcast to WebSocket clients for live UI updates.",
        tags: ["Mitel - Webhooks"],
        requestBody: new OA\RequestBody(
            required: true,
            description: "Event data from Mitel system",
            content: new OA\JsonContent(
                required: ["eventType", "data"],
                properties: [
                    new OA\Property(
                        property: "eventType",
                        type: "string",
                        enum: ["AgentStateChanged", "ConversationUpdated", "QueueStateChanged"],
                        example: "AgentStateChanged",
                        description: "Type of event being sent"
                    ),
                    new OA\Property(
                        property: "data",
                        type: "object",
                        description: "Event-specific data payload",
                        example: [
                            "agent_id" => "1234",
                            "state" => "Available",
                            "timestamp" => "2025-12-01T10:30:00Z"
                        ]
                    )
                ],
                examples: [
                    new OA\Examples(
                        example: "Agent State Changed",
                        summary: "Agent changes status",
                        value: [
                            "eventType" => "AgentStateChanged",
                            "data" => [
                                "agent_id" => "1234",
                                "state" => "Available",
                                "reason" => "Break Ended",
                                "timestamp" => "2025-12-01T10:30:00Z"
                            ]
                        ]
                    ),
                    new OA\Examples(
                        example: "Conversation Updated",
                        summary: "Call answered or ended",
                        value: [
                            "eventType" => "ConversationUpdated",
                            "data" => [
                                "agent_id" => "1234",
                                "call_id" => "call-5678",
                                "action" => "answered",
                                "phone_number" => "+60123456789",
                                "timestamp" => "2025-12-01T10:31:00Z"
                            ]
                        ]
                    ),
                    new OA\Examples(
                        example: "Queue State Changed",
                        summary: "Queue statistics updated",
                        value: [
                            "eventType" => "QueueStateChanged",
                            "data" => [
                                "queue_id" => "queue-001",
                                "calls_waiting" => 5,
                                "agents_available" => 3,
                                "timestamp" => "2025-12-01T10:32:00Z"
                            ]
                        ]
                    )
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Webhook processed successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "message", type: "string", example: "Webhook processed successfully")
                    ]
                )
            ),
            new OA\Response(
                response: 500,
                description: "Server error processing webhook",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: false),
                        new OA\Property(property: "message", type: "string", example: "Failed to process webhook"),
                        new OA\Property(property: "error", type: "string", example: "Error details")
                    ]
                )
            )
        ]
    )]
    public function handle(Request $request): JsonResponse
    {
        try {
            $eventType = $request->input('eventType');
            $eventData = $request->input('data');

            Log::info('Mitel Webhook Received', [
                'event_type' => $eventType,
                'data' => $eventData
            ]);

            // Process different event types and broadcast to WebSocket clients
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

                // Screen-pop: fired when Mitel routes a chat interaction to an agent.
                // $eventData must contain ConversationId, ConversationState, Variables, IsCleared.
                case 'MitelInteractionUpdated':
                    broadcast(new MitelInteractionUpdated($eventData));
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
