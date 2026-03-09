<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SlaConfiguration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SlaConfigurationController extends Controller
{
    /**
     * Display a listing of SLA configurations.
     */
    public function index(Request $request)
    {
        $query = SlaConfiguration::query();

        // Filter by department
        if ($request->has('department')) {
            $query->byDepartment($request->department);
        }

        // Filter by priority
        if ($request->has('priority')) {
            $query->byPriority($request->priority);
        }

        // Filter by active status
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $perPage = $request->get('per_page', 50);
        $configs = $query->orderBy('department')->orderBy('priority')->paginate($perPage);

        return response()->json($configs);
    }

    /**
     * Store a newly created SLA configuration.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'department' => 'required|string|max:255',
            'priority' => 'required|in:P1,P2,P3,P4',
            'duration_minutes' => 'required|integer|min:1',
            'compliance_target' => 'nullable|numeric|min:0|max:100',
            'business_hours_only' => 'boolean',
            'pause_on_hold' => 'boolean',
            'pause_on_pending' => 'boolean',
            'escalation_threshold_percent' => 'nullable|integer|min:0|max:100',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Check if configuration already exists for this department and priority
        $existing = SlaConfiguration::where('department', $request->department)
            ->where('priority', $request->priority)
            ->where('is_active', true)
            ->first();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'Active SLA configuration already exists for this department and priority'
            ], 422);
        }

        $config = SlaConfiguration::create($request->all());

        return response()->json([
            'success' => true,
            'message' => 'SLA configuration created successfully',
            'data' => $config
        ], 201);
    }

    /**
     * Display the specified SLA configuration.
     */
    public function show($id)
    {
        $config = SlaConfiguration::findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $config
        ]);
    }

    /**
     * Update the specified SLA configuration.
     */
    public function update(Request $request, $id)
    {
        $config = SlaConfiguration::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'department' => 'string|max:255',
            'priority' => 'in:P1,P2,P3,P4',
            'duration_minutes' => 'integer|min:1',
            'compliance_target' => 'nullable|numeric|min:0|max:100',
            'business_hours_only' => 'boolean',
            'pause_on_hold' => 'boolean',
            'pause_on_pending' => 'boolean',
            'escalation_threshold_percent' => 'nullable|integer|min:0|max:100',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $config->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'SLA configuration updated successfully',
            'data' => $config
        ]);
    }

    /**
     * Remove the specified SLA configuration.
     */
    public function destroy($id)
    {
        $config = SlaConfiguration::findOrFail($id);
        $config->delete();

        return response()->json([
            'success' => true,
            'message' => 'SLA configuration deleted successfully'
        ]);
    }

    /**
     * Get SLA for specific department and priority.
     */
    public function getSla(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'department' => 'required|string',
            'priority' => 'required|in:P1,P2,P3,P4',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $config = SlaConfiguration::getSlaForTicket($request->department, $request->priority);

        if (!$config) {
            return response()->json([
                'success' => true,
                'data' => null,
                'message' => 'No SLA configuration found for this department and priority.'
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => array_merge($config->toArray(), ['is_default' => false])
        ]);
    }

    /**
     * Get all SLA configurations grouped by department.
     */
    public function groupedByDepartment()
    {
        $configs = SlaConfiguration::active()
            ->orderBy('department')
            ->orderBy('priority')
            ->get()
            ->groupBy('department');

        return response()->json([
            'success' => true,
            'data' => $configs
        ]);
    }
}
