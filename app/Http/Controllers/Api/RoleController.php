<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\MenuItem;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'Roles',
    description: 'Role management operations for RBAC system'
)]
class RoleController extends Controller
{
    /**
     * Display a listing of roles.
     */
    #[OA\Get(
        path: '/api/v1/roles',
        summary: 'Get list of roles',
        tags: ['Roles'],
        parameters: [
            new OA\Parameter(
                name: 'search',
                description: 'Search by role name or slug',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string')
            ),
            new OA\Parameter(
                name: 'with_users',
                description: 'Include user count',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'boolean')
            ),
            new OA\Parameter(
                name: 'with_menu_items',
                description: 'Include menu items',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'boolean')
            ),
            new OA\Parameter(
                name: 'per_page',
                description: 'Items per page',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer', default: 15)
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Successful operation',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean'),
                        new OA\Property(property: 'data', type: 'object'),
                    ]
                )
            ),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Role::query();

            // Search
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('slug', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%");
                });
            }

            // Include user count
            if ($request->has('with_users') && $request->with_users) {
                $query->withCount('users');
            }

            // Include menu items
            if ($request->has('with_menu_items') && $request->with_menu_items) {
                $query->with('menuItems');
            }

            // Pagination
            $perPage = $request->input('per_page', 15);
            $roles = $query->orderBy('name')->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $roles,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch roles',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store a newly created role.
     */
    #[OA\Post(
        path: '/api/v1/roles',
        summary: 'Create a new role',
        tags: ['Roles'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', maxLength: 255, example: 'Customer Support'),
                    new OA\Property(property: 'slug', type: 'string', maxLength: 255, example: 'customer-support'),
                    new OA\Property(property: 'description', type: 'string', example: 'Customer support role with ticket management access'),
                    new OA\Property(
                        property: 'menu_items',
                        type: 'array',
                        items: new OA\Items(
                            properties: [
                                new OA\Property(property: 'menu_item_id', type: 'integer'),
                                new OA\Property(property: 'can_view', type: 'boolean', default: true),
                                new OA\Property(property: 'can_create', type: 'boolean', default: false),
                                new OA\Property(property: 'can_edit', type: 'boolean', default: false),
                                new OA\Property(property: 'can_delete', type: 'boolean', default: false),
                            ]
                        )
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Role created successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean'),
                        new OA\Property(property: 'message', type: 'string'),
                        new OA\Property(property: 'data', type: 'object'),
                    ]
                )
            ),
        ]
    )]
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:roles,slug',
            'description' => 'nullable|string',
            'menu_items' => 'nullable|array',
            'menu_items.*.menu_item_id' => 'required|integer|exists:menu_items,id',
            'menu_items.*.can_view' => 'nullable|boolean',
            'menu_items.*.can_create' => 'nullable|boolean',
            'menu_items.*.can_edit' => 'nullable|boolean',
            'menu_items.*.can_delete' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();
        try {
            // Generate slug if not provided
            $slug = $request->slug ?? Str::slug($request->name);

            // Ensure unique slug
            $originalSlug = $slug;
            $counter = 1;
            while (Role::where('slug', $slug)->exists()) {
                $slug = $originalSlug . '-' . $counter;
                $counter++;
            }

            // Create role
            $role = Role::create([
                'name' => $request->name,
                'slug' => $slug,
                'description' => $request->description,
            ]);

            // Attach menu items with permissions
            if ($request->has('menu_items') && is_array($request->menu_items)) {
                foreach ($request->menu_items as $menuItem) {
                    $role->menuItems()->attach($menuItem['menu_item_id'], [
                        'can_view' => $menuItem['can_view'] ?? true,
                        'can_create' => $menuItem['can_create'] ?? false,
                        'can_edit' => $menuItem['can_edit'] ?? false,
                        'can_delete' => $menuItem['can_delete'] ?? false,
                    ]);
                }
            }

            DB::commit();

            // Load relationships
            $role->load('menuItems');

            return response()->json([
                'success' => true,
                'message' => 'Role created successfully',
                'data' => $role,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create role',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified role.
     */
    #[OA\Get(
        path: '/api/v1/roles/{id}',
        summary: 'Get role details',
        tags: ['Roles'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'Role ID',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
            new OA\Parameter(
                name: 'with_users',
                description: 'Include users',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'boolean')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Successful operation',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean'),
                        new OA\Property(property: 'data', type: 'object'),
                    ]
                )
            ),
        ]
    )]
    public function show(Request $request, $id): JsonResponse
    {
        try {
            $role = Role::with('menuItems')->find($id);

            if (!$role) {
                return response()->json([
                    'success' => false,
                    'message' => 'Role not found',
                ], 404);
            }

            // Include users if requested
            if ($request->has('with_users') && $request->with_users) {
                $role->load('users');
            }

            return response()->json([
                'success' => true,
                'data' => $role,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch role',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update the specified role.
     */
    #[OA\Put(
        path: '/api/v1/roles/{id}',
        summary: 'Update role',
        tags: ['Roles'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'Role ID',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string', maxLength: 255),
                    new OA\Property(property: 'slug', type: 'string', maxLength: 255),
                    new OA\Property(property: 'description', type: 'string'),
                    new OA\Property(
                        property: 'menu_items',
                        type: 'array',
                        items: new OA\Items(
                            properties: [
                                new OA\Property(property: 'menu_item_id', type: 'integer'),
                                new OA\Property(property: 'can_view', type: 'boolean'),
                                new OA\Property(property: 'can_create', type: 'boolean'),
                                new OA\Property(property: 'can_edit', type: 'boolean'),
                                new OA\Property(property: 'can_delete', type: 'boolean'),
                            ]
                        )
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Role updated successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean'),
                        new OA\Property(property: 'message', type: 'string'),
                        new OA\Property(property: 'data', type: 'object'),
                    ]
                )
            ),
        ]
    )]
    public function update(Request $request, $id): JsonResponse
    {
        $role = Role::find($id);

        if (!$role) {
            return response()->json([
                'success' => false,
                'message' => 'Role not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'slug' => 'sometimes|string|max:255|unique:roles,slug,' . $id,
            'description' => 'nullable|string',
            'menu_items' => 'nullable|array',
            'menu_items.*.menu_item_id' => 'required|integer|exists:menu_items,id',
            'menu_items.*.can_view' => 'nullable|boolean',
            'menu_items.*.can_create' => 'nullable|boolean',
            'menu_items.*.can_edit' => 'nullable|boolean',
            'menu_items.*.can_delete' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();
        try {
            // Update basic fields
            $updateData = [];
            if ($request->filled('name')) {
                $updateData['name'] = $request->name;
            }
            if ($request->filled('slug')) {
                $updateData['slug'] = $request->slug;
            }
            if ($request->has('description')) {
                $updateData['description'] = $request->description;
            }

            if (!empty($updateData)) {
                $role->update($updateData);
            }

            // Update menu items with permissions
            if ($request->has('menu_items')) {
                // Detach all existing menu items
                $role->menuItems()->detach();

                // Attach new menu items
                if (is_array($request->menu_items)) {
                    foreach ($request->menu_items as $menuItem) {
                        $role->menuItems()->attach($menuItem['menu_item_id'], [
                            'can_view' => $menuItem['can_view'] ?? true,
                            'can_create' => $menuItem['can_create'] ?? false,
                            'can_edit' => $menuItem['can_edit'] ?? false,
                            'can_delete' => $menuItem['can_delete'] ?? false,
                        ]);
                    }
                }
            }

            DB::commit();

            // Reload relationships
            $role->load('menuItems');

            return response()->json([
                'success' => true,
                'message' => 'Role updated successfully',
                'data' => $role,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update role',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified role.
     */
    #[OA\Delete(
        path: '/api/v1/roles/{id}',
        summary: 'Delete role',
        tags: ['Roles'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'Role ID',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Role deleted successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean'),
                        new OA\Property(property: 'message', type: 'string'),
                    ]
                )
            ),
        ]
    )]
    public function destroy($id): JsonResponse
    {
        DB::beginTransaction();
        try {
            $role = Role::find($id);

            if (!$role) {
                return response()->json([
                    'success' => false,
                    'message' => 'Role not found',
                ], 404);
            }

            // Check if role is in use
            $usersCount = $role->users()->count();
            if ($usersCount > 0) {
                return response()->json([
                    'success' => false,
                    'message' => "Cannot delete role. It is assigned to {$usersCount} user(s)",
                ], 400);
            }

            // Detach all menu items
            $role->menuItems()->detach();

            // Delete role
            $role->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Role deleted successfully',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete role',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Assign menu items to role.
     */
    #[OA\Post(
        path: '/api/v1/roles/{id}/menu-items',
        summary: 'Assign menu items to role',
        tags: ['Roles'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'Role ID',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['menu_items'],
                properties: [
                    new OA\Property(
                        property: 'menu_items',
                        type: 'array',
                        items: new OA\Items(
                            properties: [
                                new OA\Property(property: 'menu_item_id', type: 'integer'),
                                new OA\Property(property: 'can_view', type: 'boolean', default: true),
                                new OA\Property(property: 'can_create', type: 'boolean', default: false),
                                new OA\Property(property: 'can_edit', type: 'boolean', default: false),
                                new OA\Property(property: 'can_delete', type: 'boolean', default: false),
                            ]
                        )
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Menu items assigned successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean'),
                        new OA\Property(property: 'message', type: 'string'),
                        new OA\Property(property: 'data', type: 'object'),
                    ]
                )
            ),
        ]
    )]
    public function assignMenuItems(Request $request, $id): JsonResponse
    {
        $role = Role::find($id);

        if (!$role) {
            return response()->json([
                'success' => false,
                'message' => 'Role not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'menu_items' => 'required|array',
            'menu_items.*.menu_item_id' => 'required|integer|exists:menu_items,id',
            'menu_items.*.can_view' => 'nullable|boolean',
            'menu_items.*.can_create' => 'nullable|boolean',
            'menu_items.*.can_edit' => 'nullable|boolean',
            'menu_items.*.can_delete' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();
        try {
            foreach ($request->menu_items as $menuItem) {
                $role->menuItems()->syncWithoutDetaching([
                    $menuItem['menu_item_id'] => [
                        'can_view' => $menuItem['can_view'] ?? true,
                        'can_create' => $menuItem['can_create'] ?? false,
                        'can_edit' => $menuItem['can_edit'] ?? false,
                        'can_delete' => $menuItem['can_delete'] ?? false,
                    ]
                ]);
            }

            DB::commit();

            // Reload relationships
            $role->load('menuItems');

            return response()->json([
                'success' => true,
                'message' => 'Menu items assigned successfully',
                'data' => $role,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to assign menu items',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove menu items from role.
     */
    #[OA\Delete(
        path: '/api/v1/roles/{id}/menu-items',
        summary: 'Remove menu items from role',
        tags: ['Roles'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'Role ID',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['menu_item_ids'],
                properties: [
                    new OA\Property(
                        property: 'menu_item_ids',
                        type: 'array',
                        items: new OA\Items(type: 'integer'),
                        example: [1, 2, 3]
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Menu items removed successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean'),
                        new OA\Property(property: 'message', type: 'string'),
                    ]
                )
            ),
        ]
    )]
    public function removeMenuItems(Request $request, $id): JsonResponse
    {
        $role = Role::find($id);

        if (!$role) {
            return response()->json([
                'success' => false,
                'message' => 'Role not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'menu_item_ids' => 'required|array',
            'menu_item_ids.*' => 'integer|exists:menu_items,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();
        try {
            $role->menuItems()->detach($request->menu_item_ids);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Menu items removed successfully',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to remove menu items',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get all menu items available for assignment.
     */
    #[OA\Get(
        path: '/api/v1/roles/available/menu-items',
        summary: 'Get all available menu items',
        tags: ['Roles'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Successful operation',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean'),
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(type: 'object')),
                    ]
                )
            ),
        ]
    )]
    public function getAvailableMenuItems(): JsonResponse
    {
        try {
            $menuItems = MenuItem::where('is_active', true)
                ->orderBy('parent_id')
                ->orderBy('order')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $menuItems,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch menu items',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
