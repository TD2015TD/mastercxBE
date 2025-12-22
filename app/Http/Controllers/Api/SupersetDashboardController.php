<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SupersetDashboard;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class SupersetDashboardController extends Controller
{
    /**
     * Get all dashboards (with optional filtering)
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $query = SupersetDashboard::with('roles');

        // Filter by category
        if ($request->has('category')) {
            $query->byCategory($request->category);
        }

        // Filter by active status
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Filter by public status
        if ($request->has('is_public')) {
            $query->where('is_public', $request->boolean('is_public'));
        }

        // Search by title or tags
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title_en', 'like', "%{$search}%")
                    ->orWhere('title_ms', 'like', "%{$search}%")
                    ->orWhere('tags', 'like', "%{$search}%");
            });
        }

        // Order by
        $query->ordered();

        $dashboards = $query->get();

        // Add localized fields
        $locale = $request->header('Accept-Language', 'en');
        $dashboards = $dashboards->map(function ($dashboard) use ($locale) {
            return $this->formatDashboard($dashboard, $locale);
        });

        return response()->json([
            'dashboards' => $dashboards
        ]);
    }

    /**
     * Get dashboards accessible by the authenticated user's role
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function myDashboards(Request $request)
    {
        $user = $request->user();

        if (!$user || !$user->role_id) {
            // Return public dashboards only
            $dashboards = SupersetDashboard::active()
                ->public()
                ->ordered()
                ->get();
        } else {
            // Get dashboards for user's role
            $role = Role::with('supersetDashboards')->find($user->role_id);

            if (!$role) {
                return response()->json([
                    'error' => 'Role not found'
                ], 404);
            }

            $dashboards = $role->activeSupersetDashboards()->get();
        }

        $locale = $request->header('Accept-Language', 'en');
        $dashboards = $dashboards->map(function ($dashboard) use ($locale, $user) {
            $formatted = $this->formatDashboard($dashboard, $locale);

            // Add permissions if role access exists
            if (isset($dashboard->pivot)) {
                $formatted['permissions'] = [
                    'can_view' => $dashboard->pivot->can_view,
                    'can_export' => $dashboard->pivot->can_export,
                    'can_share' => $dashboard->pivot->can_share,
                ];

                // Add custom RLS if exists
                if ($dashboard->pivot->custom_rls) {
                    $formatted['custom_rls'] = $dashboard->pivot->custom_rls;
                }
            }

            return $formatted;
        });

        return response()->json([
            'dashboards' => $dashboards
        ]);
    }

    /**
     * Get a specific dashboard by ID or slug
     *
     * @param Request $request
     * @param string $identifier
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request, string $identifier)
    {
        $dashboard = SupersetDashboard::with('roles')
            ->where('id', $identifier)
            ->orWhere('slug', $identifier)
            ->first();

        if (!$dashboard) {
            return response()->json([
                'error' => 'Dashboard not found'
            ], 404);
        }

        // Check access permissions
        $user = $request->user();
        if (!$dashboard->is_public && $user) {
            if (!$user->role_id || !$dashboard->hasRoleAccess($user->role_id)) {
                return response()->json([
                    'error' => 'Access denied'
                ], 403);
            }
        }

        $locale = $request->header('Accept-Language', 'en');
        $formatted = $this->formatDashboard($dashboard, $locale);

        // Add role permissions if applicable
        if ($user && $user->role_id) {
            $permissions = $dashboard->getRolePermissions($user->role_id);
            if ($permissions) {
                $formatted['permissions'] = [
                    'can_view' => $permissions['can_view'],
                    'can_export' => $permissions['can_export'],
                    'can_share' => $permissions['can_share'],
                ];

                if ($permissions['custom_rls']) {
                    $formatted['custom_rls'] = $permissions['custom_rls'];
                }
            }
        }

        return response()->json($formatted);
    }

    /**
     * Create a new dashboard
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'superset_id' => 'required|string|unique:superset_dashboards,superset_id',
            'slug' => 'required|string|unique:superset_dashboards,slug',
            'title_en' => 'required|string|max:255',
            'description_en' => 'nullable|string',
            'title_ms' => 'required|string|max:255',
            'description_ms' => 'nullable|string',
            'category' => 'nullable|string|max:255',
            'icon' => 'nullable|string|max:255',
            'order' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
            'is_public' => 'nullable|boolean',
            'ui_config' => 'nullable|array',
            'default_filters' => 'nullable|array',
            'rls_config' => 'nullable|array',
            'tags' => 'nullable|string',
            'thumbnail_url' => 'nullable|string|url',
            'roles' => 'nullable|array',
            'roles.*.role_id' => 'required|exists:roles,id',
            'roles.*.can_view' => 'nullable|boolean',
            'roles.*.can_export' => 'nullable|boolean',
            'roles.*.can_share' => 'nullable|boolean',
            'roles.*.custom_rls' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'details' => $validator->errors()
            ], 422);
        }

        $dashboard = SupersetDashboard::create($request->except('roles'));

        // Attach roles if provided
        if ($request->has('roles')) {
            foreach ($request->roles as $roleData) {
                $dashboard->roles()->attach($roleData['role_id'], [
                    'can_view' => $roleData['can_view'] ?? true,
                    'can_export' => $roleData['can_export'] ?? false,
                    'can_share' => $roleData['can_share'] ?? false,
                    'custom_rls' => $roleData['custom_rls'] ?? null,
                ]);
            }
        }

        $dashboard->load('roles');

        return response()->json([
            'message' => 'Dashboard created successfully',
            'dashboard' => $this->formatDashboard($dashboard)
        ], 201);
    }

    /**
     * Update a dashboard
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, int $id)
    {
        $dashboard = SupersetDashboard::find($id);

        if (!$dashboard) {
            return response()->json([
                'error' => 'Dashboard not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'superset_id' => 'sometimes|string|unique:superset_dashboards,superset_id,' . $id,
            'slug' => 'sometimes|string|unique:superset_dashboards,slug,' . $id,
            'title_en' => 'sometimes|string|max:255',
            'description_en' => 'nullable|string',
            'title_ms' => 'sometimes|string|max:255',
            'description_ms' => 'nullable|string',
            'category' => 'nullable|string|max:255',
            'icon' => 'nullable|string|max:255',
            'order' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
            'is_public' => 'nullable|boolean',
            'ui_config' => 'nullable|array',
            'default_filters' => 'nullable|array',
            'rls_config' => 'nullable|array',
            'tags' => 'nullable|string',
            'thumbnail_url' => 'nullable|string|url',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'details' => $validator->errors()
            ], 422);
        }

        $dashboard->update($request->except('roles'));

        return response()->json([
            'message' => 'Dashboard updated successfully',
            'dashboard' => $this->formatDashboard($dashboard)
        ]);
    }

    /**
     * Delete a dashboard
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(int $id)
    {
        $dashboard = SupersetDashboard::find($id);

        if (!$dashboard) {
            return response()->json([
                'error' => 'Dashboard not found'
            ], 404);
        }

        $dashboard->delete();

        return response()->json([
            'message' => 'Dashboard deleted successfully'
        ]);
    }

    /**
     * Assign roles to a dashboard
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function assignRoles(Request $request, int $id)
    {
        $dashboard = SupersetDashboard::find($id);

        if (!$dashboard) {
            return response()->json([
                'error' => 'Dashboard not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'roles' => 'required|array',
            'roles.*.role_id' => 'required|exists:roles,id',
            'roles.*.can_view' => 'nullable|boolean',
            'roles.*.can_export' => 'nullable|boolean',
            'roles.*.can_share' => 'nullable|boolean',
            'roles.*.custom_rls' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'details' => $validator->errors()
            ], 422);
        }

        // Detach all existing roles
        $dashboard->roles()->detach();

        // Attach new roles
        foreach ($request->roles as $roleData) {
            $dashboard->roles()->attach($roleData['role_id'], [
                'can_view' => $roleData['can_view'] ?? true,
                'can_export' => $roleData['can_export'] ?? false,
                'can_share' => $roleData['can_share'] ?? false,
                'custom_rls' => $roleData['custom_rls'] ?? null,
            ]);
        }

        $dashboard->load('roles');

        return response()->json([
            'message' => 'Roles assigned successfully',
            'dashboard' => $this->formatDashboard($dashboard)
        ]);
    }

    /**
     * Get all unique categories
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function categories()
    {
        $categories = SupersetDashboard::active()
            ->whereNotNull('category')
            ->distinct()
            ->pluck('category');

        return response()->json([
            'categories' => $categories
        ]);
    }

    /**
     * Format dashboard for API response
     *
     * @param SupersetDashboard $dashboard
     * @param string $locale
     * @return array
     */
    private function formatDashboard(SupersetDashboard $dashboard, string $locale = 'en'): array
    {
        return [
            'id' => $dashboard->id,
            'superset_id' => $dashboard->superset_id,
            'slug' => $dashboard->slug,
            'title' => $dashboard->getTitle($locale),
            'description' => $dashboard->getDescription($locale),
            'title_en' => $dashboard->title_en,
            'title_ms' => $dashboard->title_ms,
            'description_en' => $dashboard->description_en,
            'description_ms' => $dashboard->description_ms,
            'category' => $dashboard->category,
            'icon' => $dashboard->icon,
            'order' => $dashboard->order,
            'is_active' => $dashboard->is_active,
            'is_public' => $dashboard->is_public,
            'ui_config' => $dashboard->ui_config,
            'default_filters' => $dashboard->default_filters,
            'rls_config' => $dashboard->rls_config,
            'tags' => $dashboard->getTagsArray(),
            'thumbnail_url' => $dashboard->thumbnail_url,
            'roles' => $dashboard->roles->map(fn($role) => [
                'id' => $role->id,
                'name' => $role->name,
                'slug' => $role->slug,
            ]),
            'created_at' => $dashboard->created_at,
            'updated_at' => $dashboard->updated_at,
        ];
    }
}
