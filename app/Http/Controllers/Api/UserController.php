<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Agent;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;
use Illuminate\Support\Facades\Log;

class UserController extends Controller
{
    /**
     * Display a listing of users.
     */
    #[OA\Get(
        path: "/api/v1/users",
        summary: "Get list of users",
        description: "Retrieve paginated list of users with optional filters",
        tags: ["Users"],
        parameters: [
            new OA\Parameter(name: "role_id", in: "query", description: "Filter by role ID", schema: new OA\Schema(type: "integer")),
            new OA\Parameter(name: "status", in: "query", description: "Filter by status", schema: new OA\Schema(type: "string", enum: ["active", "inactive"])),
            new OA\Parameter(name: "search", in: "query", description: "Search by name or email", schema: new OA\Schema(type: "string")),
            new OA\Parameter(name: "per_page", in: "query", description: "Items per page", schema: new OA\Schema(type: "integer", default: 15)),
        ],
        responses: [
            new OA\Response(response: 200, description: "Success"),
            new OA\Response(response: 401, description: "Unauthenticated"),
        ]
    )]
    public function index(Request $request)
    {
        $query = User::with(['role', 'agent']);

        // Filter by role
        if ($request->has('role_id')) {
            $query->where('role_id', $request->role_id);
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Search by name or email
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Sort
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $perPage = $request->get('per_page', 15);
        $users = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $users,
        ]);
    }

    /**
     * Store a newly created user.
     */
    #[OA\Post(
        path: "/api/v1/users",
        summary: "Create a new user",
        description: "Create a new user and automatically create agent record if role is agent",
        tags: ["Users"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["name", "username", "email", "password", "role_id"],
                properties: [
                    new OA\Property(property: "name", type: "string", example: "John Doe"),
                    new OA\Property(property: "username", type: "string", example: "johndoe"),
                    new OA\Property(property: "email", type: "string", format: "email", example: "john.doe@example.com"),
                    new OA\Property(property: "password", type: "string", format: "password", example: "password123"),
                    new OA\Property(property: "role_id", type: "integer", example: 2),
                    new OA\Property(property: "status", type: "string", enum: ["active", "inactive"], example: "active"),
                    new OA\Property(property: "ignite_id", type: "string", example: "IGN12345"),
                    new OA\Property(
                        property: "agent_data",
                        description: "Agent-specific data (only for agent role)",
                        properties: [
                            new OA\Property(property: "employee_id", type: "string", example: "EMP001"),
                            new OA\Property(property: "department", type: "string", example: "Technical Support"),
                            new OA\Property(property: "designation", type: "string", example: "Senior Agent"),
                            new OA\Property(property: "mitel_agent_id", type: "string", example: "MIT123"),
                            new OA\Property(property: "mitel_extension", type: "string", example: "1001"),
                            new OA\Property(property: "mitel_queue_ids", type: "array", items: new OA\Items(type: "string")),
                            new OA\Property(property: "skills", type: "array", items: new OA\Items(type: "string")),
                            new OA\Property(property: "languages", type: "array", items: new OA\Items(type: "string")),
                            new OA\Property(property: "max_concurrent_tickets", type: "integer", example: 5),
                        ],
                        type: "object"
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: "User created successfully"),
            new OA\Response(response: 422, description: "Validation error"),
        ]
    )]
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'username' => 'required|string|max:255|unique:users,username',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'role_id' => 'required|exists:roles,id',
            'status' => 'nullable|in:active,inactive',
            'ignite_id' => 'nullable|string|unique:users,ignite_id',
            'agent_data' => 'nullable|array',
            'agent_data.employee_id' => 'nullable|string|max:50',
            'agent_data.department' => 'nullable|string|max:255',
            'agent_data.designation' => 'nullable|string|max:255',
            'agent_data.mitel_agent_id' => 'nullable|string|max:50',
            'agent_data.mitel_extension' => 'nullable|string|max:20',
            'agent_data.mitel_queue_ids' => 'nullable|array',
            'agent_data.skills' => 'nullable|array',
            'agent_data.languages' => 'nullable|array',
            'agent_data.max_concurrent_tickets' => 'nullable|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            // Create user
            $user = User::create([
                'name' => $request->name,
                'username' => $request->username,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role_id' => $request->role_id,
                'status' => $request->get('status', 'active'),
                'ignite_id' => $request->ignite_id,
            ]);

            // Check if role is agent
            $role = Role::find($request->role_id);
            if ($role && strtolower($role->slug) === 'agent') {
                // Auto-generate agent code
                $agentCode = 'AGT' . str_pad($user->id, 6, '0', STR_PAD_LEFT);

                // Prepare agent data
                $agentData = [
                    'user_id' => $user->id,
                    'agent_code' => $agentCode,
                    'employee_id' => $request->input('agent_data.employee_id'),
                    'department' => $request->input('agent_data.department', 'General'),
                    'designation' => $request->input('agent_data.designation', 'Agent'),
                    'mitel_agent_id' => $request->input('agent_data.mitel_agent_id'),
                    'mitel_extension' => $request->input('agent_data.mitel_extension'),
                    'mitel_queue_ids' => $request->input('agent_data.mitel_queue_ids', []),
                    'status' => 'active',
                    'availability' => 'offline',
                    'skills' => $request->input('agent_data.skills', []),
                    'languages' => $request->input('agent_data.languages', ['en']),
                    'max_concurrent_tickets' => $request->input('agent_data.max_concurrent_tickets', 5),
                ];

                // Create agent record
                $agent = Agent::create($agentData);

                // Load agent relationship
                $user->load('agent');
            }

            DB::commit();

            $user->load('role');

            return response()->json([
                'success' => true,
                'message' => 'User created successfully',
                'data' => $user
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to create user',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified user.
     */
    #[OA\Get(
        path: "/api/v1/users/{id}",
        summary: "Get user details",
        description: "Retrieve detailed information about a specific user",
        tags: ["Users"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, description: "User ID", schema: new OA\Schema(type: "integer")),
        ],
        responses: [
            new OA\Response(response: 200, description: "Success"),
            new OA\Response(response: 404, description: "User not found"),
        ]
    )]
    public function show($id)
    {
        $user = User::with(['role', 'agent'])->find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $user
        ]);
    }

    /**
     * Update the specified user.
     */
    #[OA\Put(
        path: "/api/v1/users/{id}",
        summary: "Update user",
        description: "Update user information and sync agent data if role is agent",
        tags: ["Users"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, description: "User ID", schema: new OA\Schema(type: "integer")),
        ],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "name", type: "string", example: "John Doe Updated"),
                    new OA\Property(property: "username", type: "string", example: "johndoe_updated"),
                    new OA\Property(property: "email", type: "string", format: "email", example: "john.updated@example.com"),
                    new OA\Property(property: "password", type: "string", format: "password", description: "Leave empty to keep current password"),
                    new OA\Property(property: "role_id", type: "integer", example: 2),
                    new OA\Property(property: "status", type: "string", enum: ["active", "inactive"]),
                    new OA\Property(
                        property: "agent_data",
                        description: "Agent-specific data (only for agent role)",
                        properties: [
                            new OA\Property(property: "employee_id", type: "string"),
                            new OA\Property(property: "department", type: "string"),
                            new OA\Property(property: "designation", type: "string"),
                            new OA\Property(property: "mitel_agent_id", type: "string"),
                            new OA\Property(property: "mitel_extension", type: "string"),
                            new OA\Property(property: "mitel_queue_ids", type: "array", items: new OA\Items(type: "string")),
                            new OA\Property(property: "skills", type: "array", items: new OA\Items(type: "string")),
                            new OA\Property(property: "languages", type: "array", items: new OA\Items(type: "string")),
                            new OA\Property(property: "max_concurrent_tickets", type: "integer"),
                        ],
                        type: "object"
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "User updated successfully"),
            new OA\Response(response: 404, description: "User not found"),
            new OA\Response(response: 422, description: "Validation error"),
        ]
    )]
    public function update(Request $request, $id)
    {
        Log::debug('Update method called for user ID: ' . $id);

        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'username' => 'sometimes|required|string|max:255|unique:users,username,' . $id,
            'email' => 'sometimes|required|email|unique:users,email,' . $id,
            'password' => 'nullable|string|min:8',
            'role_id' => 'sometimes|required|exists:roles,id',
            'status' => 'nullable|in:active,inactive',
            'ignite_id' => 'nullable|string|unique:users,ignite_id,' . $id,
            'agent_data' => 'nullable|array',
            'agent_data.employee_id' => 'nullable|string|max:50',
            'agent_data.department' => 'nullable|string|max:255',
            'agent_data.designation' => 'nullable|string|max:255',
            'agent_data.mitel_agent_id' => 'nullable|string|max:50',
            'agent_data.mitel_extension' => 'nullable|string|max:20',
            'agent_data.mitel_queue_ids' => 'nullable|array',
            'agent_data.skills' => 'nullable|array',
            'agent_data.languages' => 'nullable|array',
            'agent_data.max_concurrent_tickets' => 'nullable|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            // Update user basic info
            $userData = [];
            if ($request->has('name')) $userData['name'] = $request->name;
            if ($request->has('username')) $userData['username'] = $request->username;
            if ($request->has('email')) $userData['email'] = $request->email;
            if ($request->filled('password')) $userData['password'] = Hash::make($request->password);
            if ($request->has('role_id')) $userData['role_id'] = $request->role_id;
            if ($request->has('status')) $userData['status'] = $request->status;
            if ($request->has('ignite_id')) $userData['ignite_id'] = $request->ignite_id;

            $user->update($userData);

            // Handle role change or agent data update
            $newRoleId = $request->get('role_id', $user->role_id);
            $role = Role::find($newRoleId);

            if ($role && (strtolower($role->slug) === 'agent' || strtolower($role->slug) === 'supervisor')) {
                // Check if agent record exists
                $agent = Agent::where('user_id', $user->id)->first();

                if (!$agent) {
                    // Create new agent record if doesn't exist
                    $agentCode = 'AGT' . str_pad($user->id, 6, '0', STR_PAD_LEFT);
                    $agent = Agent::create([
                        'user_id' => $user->id,
                        'agent_code' => $agentCode,
                        'employee_id' => $request->input('agent_data.employee_id'),
                        'department' => $request->input('agent_data.department', 'General'),
                        'designation' => $request->input('agent_data.designation', 'Agent'),
                        'mitel_agent_id' => $request->input('agent_data.mitel_agent_id'),
                        'mitel_extension' => $request->input('agent_data.mitel_extension'),
                        'mitel_queue_ids' => $request->input('agent_data.mitel_queue_ids', []),
                        'status' => 'active',
                        'availability' => 'offline',
                        'skills' => $request->input('agent_data.skills', []),
                        'languages' => $request->input('agent_data.languages', ['en']),
                        'max_concurrent_tickets' => $request->input('agent_data.max_concurrent_tickets', 5),
                    ]);
                } elseif ($request->has('agent_data')) {
                    // Update existing agent record
                    $agentUpdateData = [];
                    if ($request->has('agent_data.employee_id')) $agentUpdateData['employee_id'] = $request->input('agent_data.employee_id');
                    if ($request->has('agent_data.department')) $agentUpdateData['department'] = $request->input('agent_data.department');
                    if ($request->has('agent_data.designation')) $agentUpdateData['designation'] = $request->input('agent_data.designation');
                    if ($request->has('agent_data.mitel_agent_id')) $agentUpdateData['mitel_agent_id'] = $request->input('agent_data.mitel_agent_id');
                    if ($request->has('agent_data.mitel_extension')) $agentUpdateData['mitel_extension'] = $request->input('agent_data.mitel_extension');
                    if ($request->has('agent_data.mitel_queue_ids')) $agentUpdateData['mitel_queue_ids'] = $request->input('agent_data.mitel_queue_ids');
                    if ($request->has('agent_data.skills')) $agentUpdateData['skills'] = $request->input('agent_data.skills');
                    if ($request->has('agent_data.languages')) $agentUpdateData['languages'] = $request->input('agent_data.languages');
                    if ($request->has('agent_data.max_concurrent_tickets')) $agentUpdateData['max_concurrent_tickets'] = $request->input('agent_data.max_concurrent_tickets');
                    if ($request->has('agent_data.agent_username')) $agentUpdateData['agent_username'] = $request->input('agent_data.agent_username');

                    Log::debug('Agent update payload:', $agentUpdateData);

                    if (!empty($agentUpdateData)) {
                        $agent->update($agentUpdateData);
                    }
                }
            }

            DB::commit();

            $user->load(['role', 'agent']);

            return response()->json([
                'success' => true,
                'message' => 'User updated successfully',
                'data' => $user
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to update user',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified user.
     */
    #[OA\Delete(
        path: "/api/v1/users/{id}",
        summary: "Delete user",
        description: "Soft delete a user (also soft deletes associated agent record)",
        tags: ["Users"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, description: "User ID", schema: new OA\Schema(type: "integer")),
        ],
        responses: [
            new OA\Response(response: 200, description: "User deleted successfully"),
            new OA\Response(response: 404, description: "User not found"),
        ]
    )]
    public function destroy($id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        DB::beginTransaction();
        try {
            // Soft delete associated agent if exists
            $agent = Agent::where('user_id', $user->id)->first();
            if ($agent) {
                $agent->delete();
            }

            // Soft delete user
            $user->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'User deleted successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete user',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update user's own profile.
     */
    #[OA\Put(
        path: "/api/v1/users/profile",
        summary: "Update own profile",
        description: "Allow authenticated user to update their own profile",
        tags: ["Users"],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "name", type: "string", example: "John Doe"),
                    new OA\Property(property: "email", type: "string", format: "email", example: "john.doe@example.com"),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Profile updated successfully"),
            new OA\Response(response: 422, description: "Validation error"),
        ]
    )]
    public function updateProfile(Request $request)
    {
        $user = auth()->user();

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|email|unique:users,email,' . $user->id,
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $userData = [];
            if ($request->has('name')) $userData['name'] = $request->name;
            if ($request->has('email')) $userData['email'] = $request->email;

            $user->update($userData);
            $user->load('role');

            return response()->json([
                'success' => true,
                'message' => 'Profile updated successfully',
                'data' => $user
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update profile',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Change user's password.
     */
    #[OA\Post(
        path: "/api/v1/users/change-password",
        summary: "Change password",
        description: "Allow authenticated user to change their password",
        tags: ["Users"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["current_password", "new_password"],
                properties: [
                    new OA\Property(property: "current_password", type: "string", format: "password"),
                    new OA\Property(property: "new_password", type: "string", format: "password"),
                    new OA\Property(property: "new_password_confirmation", type: "string", format: "password"),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Password changed successfully"),
            new OA\Response(response: 422, description: "Validation error"),
        ]
    )]
    public function changePassword(Request $request)
    {
        $user = auth()->user();

        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Verify current password
        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Current password is incorrect'
            ], 422);
        }

        try {
            $user->update([
                'password' => Hash::make($request->new_password)
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Password changed successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to change password',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update user status (activate/deactivate).
     */
    #[OA\Put(
        path: "/api/v1/users/{id}/status",
        summary: "Update user status",
        description: "Activate or deactivate a user account",
        tags: ["Users"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, description: "User ID", schema: new OA\Schema(type: "integer")),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["status"],
                properties: [
                    new OA\Property(property: "status", type: "string", enum: ["active", "inactive"], example: "active"),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Status updated successfully"),
            new OA\Response(response: 404, description: "User not found"),
            new OA\Response(response: 422, description: "Validation error"),
        ]
    )]
    public function updateStatus(Request $request, $id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:active,inactive',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            $user->update(['status' => $request->status]);

            // Also update agent status if user is an agent
            $agent = Agent::where('user_id', $user->id)->first();
            if ($agent) {
                $agent->update(['status' => $request->status]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'User status updated successfully',
                'data' => $user
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to update status',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
