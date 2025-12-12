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
        // Build OData query parameters
        $odataParams = [];
        try {
            if ($request->has('filter')) {
                $odataParams['$filter'] = $request->query('filter');
            }
            if ($request->has('top')) {
                $odataParams['$top'] = $request->query('top');
            }
            if ($request->has('skip')) {
                $odataParams['$skip'] = $request->query('skip');
            }

            // Get employees from Mitel service
            $data = $this->mitelService->getEmployees($odataParams);

            // Transform data for frontend based on new JSON structure
            $employees = [];
            if (isset($data['_embedded']['items']) && is_array($data['_embedded']['items'])) {
                foreach ($data['_embedded']['items'] as $employee) {
                    $employees[] = [
                        'id' => $employee['id'] ?? null,
                        'name' => $employee['name'] ?? (($employee['firstName'] ?? '') . ' ' . ($employee['lastName'] ?? '')),
                        'firstName' => $employee['firstName'] ?? null,
                        'lastName' => $employee['lastName'] ?? null,
                        'email' => $employee['email'] ?? null,
                        'username' => $employee['username'] ?? null,
                        'siteRefId' => $employee['siteRefId'] ?? null,
                        'language' => $employee['language'] ?? null,
                        'supervisorLicenseType' => $employee['supervisorLicenseType'] ?? null,
                        'photoUrl' => $employee['photoUrl'] ?? null,
                        'isEnabledForVoice' => $employee['isEnabledForVoice'] ?? null,
                        'isEnabledForEmail' => $employee['isEnabledForEmail'] ?? null,
                        'isEnabledForChat' => $employee['isEnabledForChat'] ?? null,
                        'isEnabledForSMS' => $employee['isEnabledForSMS'] ?? null,
                        'isEnabledForOpenMedia' => $employee['isEnabledForOpenMedia'] ?? null,
                        'isRealTime' => $employee['isRealTime'] ?? null,
                        'lastModified' => $employee['lastModified'] ?? null,
                        // Add more fields as needed from the JSON structure
                    ];
                }
            }
        } catch (\Exception $e) {
            Log::error('Error getting Mitel employees', [
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve agents',
                'error' => $e->getMessage()
            ], 500);
        }

        return response()->json([
            'success' => true,
            'data' => $employees ?? []
        ]);
    }
}