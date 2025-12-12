<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MitelService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

/**
 * Controller for interacting with the Mitel API via the MitelService.
 */
class MitelController extends Controller
{
    protected MitelService $mitelService;

    /**
     * MitelController constructor.
     * @param MitelService $mitelService The injected MitelService instance.
     */
    public function __construct(MitelService $mitelService)
    {
        $this->mitelService = $mitelService;
    }

    /**
     * Get a list of all employees (supports OData query parameters).
     * Maps to: MitelService::getEmployees()
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getEmployees(Request $request): JsonResponse
    {
        try {
            // Pass all query parameters (like $top, $filter, etc.) directly to the service
            $employees = $this->mitelService->getEmployees($request->query());

            return response()->json([
                'status' => 'success',
                'data' => $employees,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch employees: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get a single employee's details.
     * Maps to: MitelService::getEmployee()
     *
     * @param string $id Employee ID, username, or 'me'.
     * @return JsonResponse
     */
    public function getEmployee(string $id): JsonResponse
    {
        try {
            $employee = $this->mitelService->getEmployee($id);

            return response()->json([
                'status' => 'success',
                'data' => $employee,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => "Failed to fetch employee {$id}: " . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get an employee's presence state.
     * Maps to: MitelService::getEmployeeState()
     *
     * @param string $id Employee ID or 'me'.
     * @return JsonResponse
     */
    public function getEmployeeState(string $id): JsonResponse
    {
        try {
            $state = $this->mitelService->getEmployeeStateById($id);

            return response()->json([
                'status' => 'success',
                'data' => $state,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => "Failed to fetch employee state for {$id}: " . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update an employee's presence state.
     * Maps to: MitelService::updateEmployeeState()
     *
     * @param Request $request
     * @param string $id Employee ID or 'me'.
     * @return JsonResponse
     */
    public function updateEmployeeState(Request $request, string $id): JsonResponse
    {
        try {
            $validated = $request->validate([
                'state' => 'required|string', // e.g., 'Available', 'Busy', 'Away'
                // Add other potential state data fields as needed, e.g., 'reason'
            ]);

            $result = $this->mitelService->putEmployeeStateById($id, $validated);

            return response()->json([
                'status' => 'success',
                'message' => "Employee {$id} state updated successfully.",
                'data' => $result,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => "Failed to update employee state for {$id}: " . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get an agent's current ACD status.
     * Maps to: MitelService::getAgentStatus()
     *
     * @param string $agentId Agent ID.
     * @return JsonResponse
     */
    public function getAgentStatus(string $agentId): JsonResponse
    {
        try {
            $status = $this->mitelService->getAgentStatus($agentId);

            return response()->json([
                'status' => 'success',
                'data' => $status,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => "Failed to fetch agent status for {$agentId}: " . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Set an agent's ACD status.
     * Maps to: MitelService::setAgentStatus()
     *
     * @param Request $request
     * @param string $agentId Agent ID.
     * @return JsonResponse
     */
    public function setAgentStatus(Request $request, string $agentId): JsonResponse
    {
        try {
            $validated = $request->validate([
                'status' => 'required|string', // e.g., 'Available', 'AuxWork', 'Logout'
            ]);

            $result = $this->mitelService->setAgentStatus($agentId, $validated['status']);

            return response()->json([
                'status' => 'success',
                'message' => "Agent {$agentId} status updated to {$validated['status']}.",
                'data' => $result,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => "Failed to set agent status for {$agentId}: " . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get an agent's statistics.
     * Maps to: MitelService::getAgentStats()
     *
     * @param string $agentId Agent ID.
     * @return JsonResponse
     */
    public function getAgentStats(string $agentId): JsonResponse
    {
        try {
            $stats = $this->mitelService->getAgentStats($agentId);

            return response()->json([
                'status' => 'success',
                'data' => $stats,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => "Failed to fetch agent statistics for {$agentId}: " . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get the state of an employee by ID.
     * Maps to: MitelService::getEmployeeStateById()
     *
     * @param string $id Employee ID.
     * @return JsonResponse
     */
    public function getEmployeeStateById(string $id): JsonResponse
    {
        try {
            $state = $this->mitelService->getEmployeeStateById($id);

            return response()->json([
                'status' => 'success',
                'data' => $state,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => "Failed to fetch employee state for {$id}: " . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get all reason codes.
     * Maps to: MitelService::getReasonCodes()
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getReasonCodes(Request $request): JsonResponse
    {
        try {
            $reasonCodes = $this->mitelService->getReasonCodes($request->query());

            return response()->json([
                'status' => 'success',
                'data' => $reasonCodes,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch reason codes: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get a single reason code by ID.
     * Maps to: MitelService::getReasonCodeById()
     *
     * @param string $id Reason code ID.
     * @return JsonResponse
     */
    public function getReasonCodeById(string $id): JsonResponse
    {
        try {
            $reasonCode = $this->mitelService->getReasonCodeById($id);

            return response()->json([
                'status' => 'success',
                'data' => $reasonCode,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => "Failed to fetch reason code {$id}: " . $e->getMessage(),
            ], 500);
        }
    }
}