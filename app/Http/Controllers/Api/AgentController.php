<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AgentController extends Controller
{
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
            'daily_target' => 'nullable|integer|min:1',
            'shift_start' => 'nullable|date_format:H:i',
            'shift_end' => 'nullable|date_format:H:i',
            'working_days' => 'nullable|array',
            'notes' => 'nullable|string',
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
        $agent = Agent::find($id);

        if (!$agent) {
            return response()->json([
                'success' => false,
                'message' => 'Agent not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'department' => 'sometimes|string|max:255',
            'designation' => 'nullable|string|max:255',
            'employee_id' => 'nullable|string|unique:agents,employee_id,' . $id,
            'mitel_agent_id' => 'nullable|string|unique:agents,mitel_agent_id,' . $id,
            'mitel_extension' => 'nullable|string|max:20',
            'mitel_queue_ids' => 'nullable|array',
            'status' => 'sometimes|in:active,inactive,suspended',
            'availability' => 'sometimes|in:available,busy,away,offline',
            'skills' => 'nullable|array',
            'languages' => 'nullable|array',
            'max_concurrent_tickets' => 'nullable|integer|min:1|max:20',
            'daily_target' => 'nullable|integer|min:1',
            'shift_start' => 'nullable|date_format:H:i',
            'shift_end' => 'nullable|date_format:H:i',
            'working_days' => 'nullable|array',
            'notes' => 'nullable|string',
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
        $agent = Agent::find($id);

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

        $metrics = [
            'tickets_resolved_today' => $agent->tickets_resolved_today,
            'daily_target' => $agent->daily_target,
            'progress_percentage' => $agent->daily_target > 0 
                ? round(($agent->tickets_resolved_today / $agent->daily_target) * 100, 2)
                : 0,
            'avg_response_time' => $agent->avg_response_time,
            'sla_compliance_rate' => $agent->sla_compliance_rate,
            'customer_satisfaction' => $agent->customer_satisfaction,
        ];

        return response()->json([
            'success' => true,
            'data' => $metrics,
        ]);
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
