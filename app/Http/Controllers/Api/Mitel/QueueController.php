<?php

namespace App\Http\Controllers\Api\Mitel;

use App\Http\Controllers\Controller;
use App\Services\MitelService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use OpenApi\Attributes as OA;

/**
 * QueueController - Queue monitoring and statistics
 * 
 * Handles queue-related operations including listing queues
 * and retrieving real-time queue statistics.
 */
#[OA\Tag(
    name: "Mitel - Queues",
    description: "Queue monitoring and statistics management"
)]
class QueueController extends Controller
{
    protected MitelService $mitelService;

    public function __construct(MitelService $mitelService)
    {
        $this->mitelService = $mitelService;
    }

    /**
     * Get all available queues
     */
    #[OA\Get(
        path: "/api/v1/mitel/queues",
        summary: "Get all available queues",
        tags: ["Mitel - Queues"],
        responses: [
            new OA\Response(
                response: 200,
                description: "Queues retrieved successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(
                            property: "data",
                            type: "array",
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: "queue_id", type: "string", example: "queue-001"),
                                    new OA\Property(property: "queue_name", type: "string", example: "Customer Support"),
                                    new OA\Property(property: "agents_count", type: "integer", example: 5)
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
     * Get queue statistics
     */
    #[OA\Get(
        path: "/api/v1/mitel/queues/{queueId}/statistics",
        summary: "Get queue statistics",
        tags: ["Mitel - Queues"],
        parameters: [
            new OA\Parameter(
                name: "queueId",
                in: "path",
                required: true,
                description: "Queue ID",
                schema: new OA\Schema(type: "string")
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Queue statistics retrieved successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(
                            property: "data",
                            properties: [
                                new OA\Property(property: "queue_id", type: "string", example: "queue-001"),
                                new OA\Property(property: "calls_waiting", type: "integer", example: 3),
                                new OA\Property(property: "agents_available", type: "integer", example: 2),
                                new OA\Property(property: "average_wait_time", type: "string", example: "00:02:15"),
                                new OA\Property(property: "longest_wait_time", type: "string", example: "00:04:30")
                            ],
                            type: "object"
                        )
                    ]
                )
            ),
            new OA\Response(response: 500, description: "Server error")
        ]
    )]
    public function getStatistics(Request $request, string $queueId): JsonResponse
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
}
