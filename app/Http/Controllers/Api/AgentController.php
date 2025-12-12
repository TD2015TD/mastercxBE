<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\AgentShift;
use App\Models\AgentPerformanceMetric;
use App\Models\AgentCallStatistic;
use App\Models\User;
use App\Services\MitelService;
use App\Events\AgentStateChanged;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;

class AgentController extends Controller
{
    protected MitelService $mitelService;

    public function __construct(MitelService $mitelService)
    {
        $this->mitelService = $mitelService;
    }

    /**
     * Display a listing of agents.
     */
    public function index(Request $request)
    {
        $query = Agent::with('user');

        // Filter by department
        if ($request->has('department')) {
            $query->byDepartment($request->department);
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by availability
        if ($request->has('availability')) {
            $query->where('availability', $request->availability);
        }

        // Search by name, email, or agent code
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('agent_code', 'like', "%{$search}%")
                  ->orWhere('employee_id', 'like', "%{$search}%")
                  ->orWhereHas('user', function($userQuery) use ($search) {
                      $userQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                  });
            });
        }

        // Sort
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $perPage = $request->get('per_page', 15);
        $agents = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $agents,
        ]);
    }

    /**
     * Store a newly created agent.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id|unique:agents,user_id',
            'department' => 'required|string|max:255',
            'designation' => 'nullable|string|max:255',
            'employee_id' => 'nullable|string|unique:agents,employee_id',
            'mitel_agent_id' => 'nullable|string|unique:agents,mitel_agent_id',
            'mitel_extension' => 'nullable|string|max:20',
            'mitel_queue_ids' => 'nullable|array',
            'skills' => 'nullable|array',
            'languages' => 'nullable|array',
            'max_concurrent_tickets' => 'nullable|integer|min:1|max:20',
            'notes' => 'nullable|string',
            'agent_username' => 'nullable|string|unique:agents,agent_username',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();
        try {
            // Generate unique agent code
            $agentCode = $this->generateAgentCode($request->department);

            $agent = Agent::create(array_merge($validator->validated(), [
                'agent_code' => $agentCode,
                'status' => 'active',
                'availability' => 'offline',
            ]));

            $agent->load('user');

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Agent created successfully',
                'data' => $agent,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create agent',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified agent.
     */
    public function show(string $id)
    {
        $agent = Agent::with('user')->find($id);

        if (!$agent) {
            return response()->json([
                'success' => false,
                'message' => 'Agent not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $agent,
        ]);
    }

    /**
     * Update the specified agent.
     */
    public function update(Request $request, string $id)
    {
        $agent = Agent::where('user_id', $id)->first();

        if (!$agent) {
            return response()->json([
                'success' => false,
                'message' => 'Agent not found',
            ], 404);
        }

        $agentData = $request->input('agent_data', []);

        $validator = Validator::make($agentData, [
            'department' => 'sometimes|string|max:255',
            'designation' => 'nullable|string|max:255',
            'employee_id' => 'nullable|string|unique:agents,employee_id,' . $agent->id,
            'mitel_agent_id' => 'nullable|string|unique:agents,mitel_agent_id,' . $agent->id,
            'mitel_extension' => 'nullable|string|max:20',
            'mitel_queue_ids' => 'nullable|array',
            'status' => 'sometimes|in:active,inactive,suspended',
            'availability' => 'sometimes|in:available,busy,away,offline',
            'skills' => 'nullable|array',
            'languages' => 'nullable|array',
            'max_concurrent_tickets' => 'nullable|integer|min:1|max:20',
            'notes' => 'nullable|string',
            'agent_username' => 'nullable|string|unique:agents,agent_username,' . $agent->id,
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $agent->update($validator->validated());
            $agent->load('user');

            return response()->json([
                'success' => true,
                'message' => 'Agent updated successfully',
                'data' => $agent,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update agent',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified agent.
     */
    public function destroy(string $id)
    {
        $agent = Agent::where('user_id', $id)->first();
        Log::debug(("Agent is " . json_encode($agent)));
        if (!$agent) {
            return response()->json([
                'success' => false,
                'message' => 'Agent not found',
            ], 404);
        }

        try {
            $agent->delete();

            return response()->json([
                'success' => true,
                'message' => 'Agent deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete agent',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update agent availability status.
     */
    public function updateAvailability(Request $request, string $id)
    {
        $validator = Validator::make($request->all(), [
            'availability' => 'required|in:available,busy,away,offline',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $agent = Agent::find($id);

        if (!$agent) {
            return response()->json([
                'success' => false,
                'message' => 'Agent not found',
            ], 404);
        }

        $agent->update([
            'availability' => $request->availability,
            'last_activity_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Availability updated successfully',
            'data' => $agent,
        ]);
    }

    /**
     * Get agent performance metrics.
     */
    public function metrics(string $id)
    {
        $agent = Agent::find($id);

        if (!$agent) {
            return response()->json([
                'success' => false,
                'message' => 'Agent not found',
            ], 404);
        }

        // Get today's performance metrics from the new table
        $todayPerformance = $agent->todayPerformance();

        $metrics = $todayPerformance ? [
            'tickets_resolved' => $todayPerformance->tickets_resolved,
            'daily_target' => $todayPerformance->daily_target,
            'progress_percentage' => $todayPerformance->daily_target > 0 
                ? round(($todayPerformance->tickets_resolved / $todayPerformance->daily_target) * 100, 2)
                : 0,
            'avg_response_time' => $todayPerformance->avg_response_time,
            'sla_compliance_rate' => $todayPerformance->sla_compliance_rate,
            'customer_satisfaction' => $todayPerformance->customer_satisfaction_score,
            'target_achievement_rate' => $todayPerformance->target_achievement_rate,
        ] : [
            'message' => 'No performance data available for today'
        ];

        return response()->json([
            'success' => true,
            'data' => $metrics,
        ]);
    }

    // ==================== Mitel Telephony Integration ====================

    /**
     * Get current agent Mitel state
     */
    #[OA\Get(
        path: "/api/v1/agents/{id}/mitel/state",
        summary: "Get agent current Mitel state",
        tags: ["Agents - Mitel"],
        parameters: [
            new OA\Parameter(
                name: "id",
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
            new OA\Response(response: 404, description: "Agent not found"),
            new OA\Response(response: 500, description: "Server error")
        ]
    )]
    public function getMitelState(Request $request, string $id): JsonResponse
    {
        $agent = Agent::where('user_id', $id)->first();
       
        if (!$agent || !$agent->employee_id) {
            return response()->json([
                'success' => false,
                'message' => 'Agent not found or not configured for Mitel',
            ], 404);
        }

        try {
            $data = $this->mitelService->getEmployeeState($agent->employee_id);
            $state = null;
            if (isset($data['presence']['aggregate']['state'])) {
                $state = $data['presence']['aggregate']['state'];
            }
            return response()->json([
                'success' => true,
                'state' => $state,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            Log::error('Error getting employee Mitel state', [
                'user_id' => $id,
                'mitel_employee_id' => $agent->employee_id,
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve employee state',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update agent Mitel state
     */
    #[OA\Put(
        path: "/api/v1/agents/{id}/mitel/state",
        summary: "Update agent Mitel state",
        tags: ["Agents - Mitel"],
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
                name: "id",
                in: "path",
                required: true,
                description: "Agent ID",
                schema: new OA\Schema(type: "string")
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Agent state updated successfully"
            ),
            new OA\Response(response: 404, description: "Agent not found"),
            new OA\Response(response: 422, description: "Validation error"),
            new OA\Response(response: 500, description: "Server error")
        ]
    )]
    public function updateMitelState(Request $request, string $id): JsonResponse
    {
        $agent = Agent::where('user_id', $id)->first();

        if (!$agent || !$agent->employee_id) {
            return response()->json([
                'success' => false,
                'message' => 'Agent not found or not configured for Mitel',
            ], 404);
        }

        // Accept frontend payload (all PresenceStateUpdateModel fields)
        $validated = $request->validate([
            'State' => 'required|integer',
            'AvailableReason' => 'required|integer',
            'IsHotDeskExternal' => 'required|boolean',
            'Reason' => 'nullable|string',
            'DeviceId' => 'nullable|string',
            'HotDeskBaseExtension' => 'nullable|string',
            'HotDeskPin' => 'nullable|string',
            'HotDeskAgentReporting' => 'nullable|string',
            'IsCloudlinkAuthenticated' => 'nullable|boolean',
        ]);

        // Map State (int) to Mitel state string for local update only
        $stateMap = [
            1 => 'Available',
            2 => 'Busy',
            3 => 'DoNotDisturb',
            4 => 'Away',
            5 => 'Logout',
        ];
        $stateString = $stateMap[$validated['State']] ?? null;
        if (!$stateString) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid state value',
            ], 422);
        }

        // Build Mitel payload (all fields as per PresenceStateUpdateModel)
        $mitelPayload = [
            'State' => $validated['State'],
            'AvailableReason' => $validated['AvailableReason'],
            'IsHotDeskExternal' => $validated['IsHotDeskExternal'],
        ];
        if (isset($validated['Reason'])) {
            $mitelPayload['Reason'] = $validated['Reason'];
        }
        if (isset($validated['DeviceId'])) {
            $mitelPayload['DeviceId'] = $validated['DeviceId'];
        }
        if (isset($validated['HotDeskBaseExtension'])) {
            $mitelPayload['HotDeskBaseExtension'] = $validated['HotDeskBaseExtension'];
        }
        if (isset($validated['HotDeskPin'])) {
            $mitelPayload['HotDeskPin'] = $validated['HotDeskPin'];
        }
        if (isset($validated['HotDeskAgentReporting'])) {
            $mitelPayload['HotDeskAgentReporting'] = $validated['HotDeskAgentReporting'];
        }
        if (isset($validated['IsCloudlinkAuthenticated'])) {
            $mitelPayload['IsCloudlinkAuthenticated'] = $validated['IsCloudlinkAuthenticated'];
        }

        try {
            // Pass full PresenceStateUpdateModel payload to Mitel
            $data = $this->mitelService->updateEmployeeState($agent->employee_id, $mitelPayload);

            // Update local agent state using mapped string
            $agent->updateMitelState($stateString, $validated['Reason'] ?? null);

            // Broadcast agent state change event
            broadcast(new AgentStateChanged([
                'user_id' => $id,
                'mitel_employee_id' => $agent->employee_id,
                'state' => $stateString,
                'reason' => $validated['Reason'] ?? null,
                'timestamp' => now()
            ]));

            return response()->json([
                'success' => true,
                'message' => 'Employee state updated successfully',
                'data' => [
                    'user_id' => $id,
                    'state' => $stateString,
                    'mitel_response' => $data
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error updating employee Mitel state', [
                'user_id' => $id,
                'mitel_employee_id' => $agent->mitel_agent_id,
                'state' => $stateString,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update employee state',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get agent call statistics from Mitel
     */
    #[OA\Get(
        path: "/api/v1/agents/{id}/mitel/statistics",
        summary: "Get agent call statistics from Mitel",
        tags: ["Agents - Mitel"],
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                required: true,
                description: "Agent ID",
                schema: new OA\Schema(type: "string")
            ),
            new OA\Parameter(
                name: "date",
                in: "query",
                required: false,
                description: "Date for statistics (Y-m-d format). Defaults to today",
                schema: new OA\Schema(type: "string", format: "date")
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Statistics retrieved successfully"
            ),
            new OA\Response(response: 404, description: "Agent not found"),
            new OA\Response(response: 500, description: "Server error")
        ]
    )]
    public function getMitelStatistics(Request $request, string $id): JsonResponse
    {
        $agent = Agent::where('user_id', $id)->first();

        if (!$agent || !$agent->employee_id) {
            return response()->json([
                'success' => false,
                'message' => 'Agent not found or not configured for Mitel',
            ], 404);
        }

        try {
            $date = $request->input('date', today()->format('Y-m-d'));
            // Get from local database first
            $callStats = $agent->callStatistics()
                ->whereDate('statistic_date', $date)
                ->first();

            if ($callStats) {
                return response()->json([
                    'success' => true,
                    'data' => $callStats->summary,
                    'source' => 'database'
                ]);
            }

            // Fall back to Mitel API for real-time data
            $data = $this->mitelService->getEmployeeState($agent->employee_id);

            return response()->json([
                'success' => true,
                'data' => $data,
                'source' => 'mitel_api'
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting employee call statistics', [
                'user_id' => $id,
                'mitel_employee_id' => $agent->employee_id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve employee statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // ==================== Shift Management ====================

    /**
     * Get agent shift schedule
     */
    #[OA\Get(
        path: "/api/v1/agents/{id}/shifts",
        summary: "Get agent shift schedule",
        tags: ["Agents - Shifts"],
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                required: true,
                description: "Agent ID",
                schema: new OA\Schema(type: "string")
            ),
            new OA\Parameter(
                name: "start_date",
                in: "query",
                required: false,
                description: "Start date (Y-m-d)",
                schema: new OA\Schema(type: "string", format: "date")
            ),
            new OA\Parameter(
                name: "end_date",
                in: "query",
                required: false,
                description: "End date (Y-m-d)",
                schema: new OA\Schema(type: "string", format: "date")
            )
        ],
        responses: [
            new OA\Response(response: 200, description: "Shifts retrieved successfully"),
            new OA\Response(response: 404, description: "Agent not found")
        ]
    )]
    public function getShifts(Request $request, string $id): JsonResponse
    {
        $agent = Agent::find($id);

        if (!$agent) {
            return response()->json([
                'success' => false,
                'message' => 'Agent not found',
            ], 404);
        }

        $query = $agent->shifts();

        if ($request->has('start_date') && $request->has('end_date')) {
            $query->dateRange($request->start_date, $request->end_date);
        } elseif ($request->has('date')) {
            $query->forDate($request->date);
        } else {
            // Default to current week
            $query->dateRange(now()->startOfWeek(), now()->endOfWeek());
        }

        $shifts = $query->orderBy('shift_date')->get();

        return response()->json([
            'success' => true,
            'data' => $shifts->map(fn($shift) => $shift->summary),
        ]);
    }

    /**
     * Create or update agent shift
     */
    #[OA\Post(
        path: "/api/v1/agents/{id}/shifts",
        summary: "Create or update agent shift",
        tags: ["Agents - Shifts"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["shift_date"],
                properties: [
                    new OA\Property(property: "shift_date", type: "string", format: "date", example: "2025-12-01"),
                    new OA\Property(property: "shift_start", type: "string", format: "time", example: "09:00"),
                    new OA\Property(property: "shift_end", type: "string", format: "time", example: "17:00"),
                    new OA\Property(property: "shift_type", type: "string", enum: ["morning", "afternoon", "evening", "night"], example: "morning"),
                    new OA\Property(property: "break_duration", type: "integer", example: 60),
                    new OA\Property(property: "is_off_day", type: "boolean", example: false),
                    new OA\Property(property: "notes", type: "string", example: "Regular shift")
                ]
            )
        ),
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                required: true,
                description: "Agent ID",
                schema: new OA\Schema(type: "string")
            )
        ],
        responses: [
            new OA\Response(response: 200, description: "Shift created/updated successfully"),
            new OA\Response(response: 404, description: "Agent not found"),
            new OA\Response(response: 422, description: "Validation error")
        ]
    )]
    public function storeShift(Request $request, string $id): JsonResponse
    {
        $agent = Agent::find($id);

        if (!$agent) {
            return response()->json([
                'success' => false,
                'message' => 'Agent not found',
            ], 404);
        }

        $validated = $request->validate([
            'shift_date' => 'required|date',
            'shift_start' => 'nullable|date_format:H:i',
            'shift_end' => 'nullable|date_format:H:i',
            'shift_type' => 'nullable|in:morning,afternoon,evening,night',
            'break_duration' => 'nullable|integer|min:0',
            'is_off_day' => 'nullable|boolean',
            'notes' => 'nullable|string',
        ]);

        try {
            $shift = AgentShift::updateOrCreate(
                [
                    'agent_id' => $id,
                    'shift_date' => $validated['shift_date']
                ],
                $validated
            );

            return response()->json([
                'success' => true,
                'message' => 'Shift saved successfully',
                'data' => $shift->summary,
            ]);
        } catch (\Exception $e) {
            Log::error('Error saving agent shift', [
                'agent_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to save shift',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // ==================== Performance Metrics ====================

    /**
     * Get agent performance metrics
     */
    #[OA\Get(
        path: "/api/v1/agents/{id}/performance",
        summary: "Get agent performance metrics",
        tags: ["Agents - Performance"],
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                required: true,
                description: "Agent ID",
                schema: new OA\Schema(type: "string")
            ),
            new OA\Parameter(
                name: "start_date",
                in: "query",
                required: false,
                description: "Start date (Y-m-d)",
                schema: new OA\Schema(type: "string", format: "date")
            ),
            new OA\Parameter(
                name: "end_date",
                in: "query",
                required: false,
                description: "End date (Y-m-d)",
                schema: new OA\Schema(type: "string", format: "date")
            )
        ],
        responses: [
            new OA\Response(response: 200, description: "Performance metrics retrieved successfully"),
            new OA\Response(response: 404, description: "Agent not found")
        ]
    )]
    public function getPerformance(Request $request, string $id): JsonResponse
    {
        $agent = Agent::find($id);

        if (!$agent) {
            return response()->json([
                'success' => false,
                'message' => 'Agent not found',
            ], 404);
        }

        $query = $agent->performanceMetrics();

        if ($request->has('start_date') && $request->has('end_date')) {
            $query->dateRange($request->start_date, $request->end_date);
        } elseif ($request->has('date')) {
            $query->forDate($request->date);
        } else {
            // Default to today
            $query->today();
        }

        $metrics = $query->orderBy('metric_date', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => $metrics->map(fn($metric) => $metric->summary),
        ]);
    }

    /**
     * Create or update agent performance metrics
     */
    #[OA\Post(
        path: "/api/v1/agents/{id}/performance",
        summary: "Create or update agent performance metrics",
        tags: ["Agents - Performance"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["metric_date"],
                properties: [
                    new OA\Property(property: "metric_date", type: "string", format: "date", example: "2025-12-01"),
                    new OA\Property(property: "tickets_resolved", type: "integer", example: 12),
                    new OA\Property(property: "tickets_assigned", type: "integer", example: 15),
                    new OA\Property(property: "tickets_pending", type: "integer", example: 3),
                    new OA\Property(property: "daily_target", type: "integer", example: 10),
                    new OA\Property(property: "avg_response_time", type: "integer", example: 300),
                    new OA\Property(property: "sla_compliance_rate", type: "number", format: "float", example: 95.5),
                    new OA\Property(property: "customer_satisfaction_score", type: "number", format: "float", example: 4.5)
                ]
            )
        ),
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                required: true,
                description: "Agent ID",
                schema: new OA\Schema(type: "string")
            )
        ],
        responses: [
            new OA\Response(response: 200, description: "Performance metrics saved successfully"),
            new OA\Response(response: 404, description: "Agent not found"),
            new OA\Response(response: 422, description: "Validation error")
        ]
    )]
    public function storePerformance(Request $request, string $id): JsonResponse
    {
        $agent = Agent::find($id);

        if (!$agent) {
            return response()->json([
                'success' => false,
                'message' => 'Agent not found',
            ], 404);
        }

        $validated = $request->validate([
            'metric_date' => 'required|date',
            'tickets_resolved' => 'nullable|integer|min:0',
            'tickets_assigned' => 'nullable|integer|min:0',
            'tickets_pending' => 'nullable|integer|min:0',
            'daily_target' => 'nullable|integer|min:0',
            'avg_first_response_time' => 'nullable|integer|min:0',
            'avg_resolution_time' => 'nullable|integer|min:0',
            'avg_response_time' => 'nullable|integer|min:0',
            'sla_compliance_rate' => 'nullable|numeric|min:0|max:100',
            'customer_satisfaction_score' => 'nullable|numeric|min:0|max:5',
            'customer_satisfaction_count' => 'nullable|integer|min:0',
            'reopened_tickets' => 'nullable|integer|min:0',
            'total_interactions' => 'nullable|integer|min:0',
            'avg_handle_time' => 'nullable|integer|min:0',
            'first_contact_resolution_rate' => 'nullable|numeric|min:0|max:100',
        ]);

        try {
            $metric = AgentPerformanceMetric::updateOrCreate(
                [
                    'agent_id' => $id,
                    'metric_date' => $validated['metric_date']
                ],
                $validated
            );

            $metric->calculateMetrics();

            return response()->json([
                'success' => true,
                'message' => 'Performance metrics saved successfully',
                'data' => $metric->summary,
            ]);
        } catch (\Exception $e) {
            Log::error('Error saving agent performance metrics', [
                'agent_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to save performance metrics',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // ==================== Call Statistics ====================

    /**
     * Get agent call statistics
     */
    #[OA\Get(
        path: "/api/v1/agents/{id}/call-statistics",
        summary: "Get agent call statistics",
        tags: ["Agents - Call Statistics"],
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                required: true,
                description: "Agent ID",
                schema: new OA\Schema(type: "string")
            ),
            new OA\Parameter(
                name: "start_date",
                in: "query",
                required: false,
                description: "Start date (Y-m-d)",
                schema: new OA\Schema(type: "string", format: "date")
            ),
            new OA\Parameter(
                name: "end_date",
                in: "query",
                required: false,
                description: "End date (Y-m-d)",
                schema: new OA\Schema(type: "string", format: "date")
            )
        ],
        responses: [
            new OA\Response(response: 200, description: "Call statistics retrieved successfully"),
            new OA\Response(response: 404, description: "Agent not found")
        ]
    )]
    public function getCallStatistics(Request $request, string $id): JsonResponse
    {
        $agent = Agent::find($id);

        if (!$agent) {
            return response()->json([
                'success' => false,
                'message' => 'Agent not found',
            ], 404);
        }

        $query = $agent->callStatistics();

        if ($request->has('start_date') && $request->has('end_date')) {
            $query->dateRange($request->start_date, $request->end_date);
        } elseif ($request->has('date')) {
            $query->forDate($request->date);
        } else {
            // Default to today
            $query->today();
        }

        $statistics = $query->orderBy('statistic_date', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => $statistics->map(fn($stat) => $stat->summary),
        ]);
    }

    /**
     * Create or update agent call statistics
     */
    #[OA\Post(
        path: "/api/v1/agents/{id}/call-statistics",
        summary: "Create or update agent call statistics",
        tags: ["Agents - Call Statistics"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["statistic_date"],
                properties: [
                    new OA\Property(property: "statistic_date", type: "string", format: "date", example: "2025-12-01"),
                    new OA\Property(property: "total_calls", type: "integer", example: 45),
                    new OA\Property(property: "answered_calls", type: "integer", example: 42),
                    new OA\Property(property: "missed_calls", type: "integer", example: 3),
                    new OA\Property(property: "total_talk_time", type: "integer", example: 7200),
                    new OA\Property(property: "avg_call_duration", type: "integer", example: 300)
                ]
            )
        ),
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                required: true,
                description: "Agent ID",
                schema: new OA\Schema(type: "string")
            )
        ],
        responses: [
            new OA\Response(response: 200, description: "Call statistics saved successfully"),
            new OA\Response(response: 404, description: "Agent not found"),
            new OA\Response(response: 422, description: "Validation error")
        ]
    )]
    public function storeCallStatistics(Request $request, string $id): JsonResponse
    {
        $agent = Agent::find($id);

        if (!$agent) {
            return response()->json([
                'success' => false,
                'message' => 'Agent not found',
            ], 404);
        }

        $validated = $request->validate([
            'statistic_date' => 'required|date',
            'total_calls' => 'nullable|integer|min:0',
            'answered_calls' => 'nullable|integer|min:0',
            'missed_calls' => 'nullable|integer|min:0',
            'transferred_calls' => 'nullable|integer|min:0',
            'outbound_calls' => 'nullable|integer|min:0',
            'total_talk_time' => 'nullable|integer|min:0',
            'avg_call_duration' => 'nullable|integer|min:0',
            'longest_call_duration' => 'nullable|integer|min:0',
            'shortest_call_duration' => 'nullable|integer|min:0',
            'total_acw_time' => 'nullable|integer|min:0',
            'avg_acw_time' => 'nullable|integer|min:0',
            'available_time' => 'nullable|integer|min:0',
            'busy_time' => 'nullable|integer|min:0',
            'away_time' => 'nullable|integer|min:0',
            'dnd_time' => 'nullable|integer|min:0',
            'logged_in_time' => 'nullable|integer|min:0',
        ]);

        try {
            $statistic = AgentCallStatistic::updateOrCreate(
                [
                    'agent_id' => $id,
                    'statistic_date' => $validated['statistic_date']
                ],
                $validated
            );

            $statistic->calculateMetrics();

            return response()->json([
                'success' => true,
                'message' => 'Call statistics saved successfully',
                'data' => $statistic->summary,
            ]);
        } catch (\Exception $e) {
            Log::error('Error saving agent call statistics', [
                'agent_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to save call statistics',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generate unique agent code.
     */
    private function generateAgentCode(string $department): string
    {
        $departmentCode = strtoupper(substr($department, 0, 3));
        $randomCode = strtoupper(Str::random(6));
        $agentCode = "{$departmentCode}-{$randomCode}";

        // Ensure uniqueness
        while (Agent::where('agent_code', $agentCode)->exists()) {
            $randomCode = strtoupper(Str::random(6));
            $agentCode = "{$departmentCode}-{$randomCode}";
        }

        return $agentCode;
    }
}
