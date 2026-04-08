<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PermissionController extends Controller
{
    /**
     * Display a listing of permissions.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Permission::query()->orderBy('module')->orderBy('order')->orderBy('name');

        // Filter by module
        if ($request->has('module')) {
            $query->module($request->module);
        }

        // Filter by active status
        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->boolean('paginate', true)) {
            $permissions = $query->paginate($request->input('per_page', 50));
            return response()->json($permissions);
        }

        $permissions = $query->get();
        return response()->json($permissions);
    }

    /**
     * Get permissions grouped by module.
     */
    public function grouped(): JsonResponse
    {
        $permissions = Permission::active()
            ->orderBy('module')
            ->orderBy('order')
            ->orderBy('name')
            ->get();

        $grouped = $permissions->groupBy('module')->map(function ($items) {
            return $items->values();
        });

        return response()->json($grouped);
    }

    /**
     * Store a newly created permission.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:permissions,slug',
            'module' => 'required|string|max:255',
            'description' => 'nullable|string',
            'order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $permission = Permission::create($validator->validated());

        return response()->json([
            'message' => 'Permission created successfully',
            'permission' => $permission
        ], 201);
    }

    /**
     * Display the specified permission.
     */
    public function show(Permission $permission): JsonResponse
    {
        return response()->json($permission->load('roles'));
    }

    /**
     * Update the specified permission.
     */
    public function update(Request $request, Permission $permission): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'slug' => 'sometimes|required|string|max:255|unique:permissions,slug,' . $permission->id,
            'module' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $permission->update($validator->validated());

        return response()->json([
            'message' => 'Permission updated successfully',
            'permission' => $permission
        ]);
    }

    /**
     * Remove the specified permission.
     */
    public function destroy(Permission $permission): JsonResponse
    {
        $permission->delete();

        return response()->json([
            'message' => 'Permission deleted successfully'
        ]);
    }

    /**
     * Get permissions for a specific role.
     */
    public function rolePermissions(Role $role): JsonResponse
    {
        $permissions = $role->permissions()->get();

        return response()->json($permissions);
    }

    /**
     * Sync permissions for a role.
     */
    public function syncRolePermissions(Request $request, Role $role): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'permissions' => 'required|array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            $role->permissions()->sync($request->permissions);
            
            DB::commit();

            return response()->json([
                'message' => 'Role permissions updated successfully',
                'permissions' => $role->permissions()->get()
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'message' => 'Failed to update role permissions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get permission matrix for all roles.
     */
    public function matrix(): JsonResponse
    {
        $roles = Role::with('permissions')->get();
        $permissions = Permission::active()
            ->orderBy('module')
            ->orderBy('order')
            ->orderBy('name')
            ->get();

        // Create a matrix structure
        $matrix = [];
        foreach ($permissions->groupBy('module') as $module => $modulePermissions) {
            $permissionsArray = [];
            
            foreach ($modulePermissions as $permission) {
                $rolePermissions = [];
                
                foreach ($roles as $role) {
                    $rolePermissions[$role->slug] = $role->permissions->contains($permission->id);
                }
                
                $permissionsArray[] = [
                    'id' => $permission->id,
                    'name' => $permission->name,
                    'slug' => $permission->slug,
                    'description' => $permission->description,
                    'roles' => $rolePermissions
                ];
            }
            
            $matrix[] = [
                'module' => $module,
                'permissions' => $permissionsArray
            ];
        }

        return response()->json([
            'roles' => $roles->map(fn($role) => [
                'id' => $role->id,
                'name' => $role->name,
                'slug' => $role->slug,
            ]),
            'matrix' => $matrix
        ]);
    }
}
