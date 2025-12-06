<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MenuItem;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class MenuController extends Controller
{
    /**
     * Get menu items for the authenticated user's role.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user || !$user->role_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'User role not found',
                    'data' => []
                ], 401);
            }

            // Cache menu items per role for 1 hour
            $cacheKey = "menu_items_role_{$user->role_id}";
            
            $menuItems = Cache::remember($cacheKey, 3600, function () use ($user) {
                $role = Role::with(['activeMenuItems' => function ($query) {
                    $query->whereNull('parent_id') // Only top-level items
                          ->with('children') // Include sub-items
                          ->ordered();
                }])->find($user->role_id);

                if (!$role) {
                    return [];
                }

                return $role->activeMenuItems->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'label' => $item->label,
                        'path' => $item->path,
                        'icon' => $item->icon,
                        'description' => $item->description,
                        'tooltip' => $item->tooltip,
                        'order' => $item->order,
                        'metadata' => $item->metadata,
                        'permissions' => [
                            'can_view' => $item->pivot->can_view,
                            'can_create' => $item->pivot->can_create,
                            'can_edit' => $item->pivot->can_edit,
                            'can_delete' => $item->pivot->can_delete,
                        ],
                        'children' => $item->children->map(function ($child) {
                            return [
                                'id' => $child->id,
                                'label' => $child->label,
                                'path' => $child->path,
                                'icon' => $child->icon,
                                'description' => $child->description,
                                'tooltip' => $child->tooltip,
                                'order' => $child->order,
                                'metadata' => $child->metadata,
                            ];
                        }),
                    ];
                });
            });

            return response()->json([
                'success' => true,
                'message' => 'Menu items retrieved successfully',
                'data' => $menuItems
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve menu items',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get menu items by role slug (for admin purposes).
     */
    public function getByRole(Request $request, string $roleSlug): JsonResponse
    {
        try {
            $role = Role::where('slug', $roleSlug)
                ->with(['activeMenuItems' => function ($query) {
                    $query->whereNull('parent_id')
                          ->with('children')
                          ->ordered();
                }])
                ->first();

            if (!$role) {
                return response()->json([
                    'success' => false,
                    'message' => 'Role not found',
                    'data' => []
                ], 404);
            }

            $menuItems = $role->activeMenuItems->map(function ($item) {
                return [
                    'id' => $item->id,
                    'label' => $item->label,
                    'path' => $item->path,
                    'icon' => $item->icon,
                    'description' => $item->description,
                    'tooltip' => $item->tooltip,
                    'order' => $item->order,
                    'metadata' => $item->metadata,
                    'permissions' => [
                        'can_view' => $item->pivot->can_view,
                        'can_create' => $item->pivot->can_create,
                        'can_edit' => $item->pivot->can_edit,
                        'can_delete' => $item->pivot->can_delete,
                    ],
                    'children' => $item->children->map(function ($child) {
                        return [
                            'id' => $child->id,
                            'label' => $child->label,
                            'path' => $child->path,
                            'icon' => $child->icon,
                            'description' => $child->description,
                            'tooltip' => $child->tooltip,
                            'order' => $child->order,
                            'metadata' => $child->metadata,
                        ];
                    }),
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Menu items retrieved successfully',
                'data' => [
                    'role' => [
                        'id' => $role->id,
                        'name' => $role->name,
                        'slug' => $role->slug,
                    ],
                    'menu_items' => $menuItems
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve menu items',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Clear menu cache for all roles.
     */
    public function clearCache(): JsonResponse
    {
        try {
            $roles = Role::all();
            
            foreach ($roles as $role) {
                Cache::forget("menu_items_role_{$role->id}");
            }

            return response()->json([
                'success' => true,
                'message' => 'Menu cache cleared successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to clear cache',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all menu items (admin only).
     */
    public function all(): JsonResponse
    {
        try {
            $menuItems = MenuItem::with(['children' => function ($query) {
                $query->ordered();
            }])
            ->topLevel()
            ->ordered()
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'label' => $item->label,
                    'path' => $item->path,
                    'icon' => $item->icon,
                    'description' => $item->description,
                    'tooltip' => $item->tooltip,
                    'order' => $item->order,
                    'is_active' => $item->is_active,
                    'metadata' => $item->metadata,
                    'children' => $item->children->map(function ($child) {
                        return [
                            'id' => $child->id,
                            'label' => $child->label,
                            'path' => $child->path,
                            'icon' => $child->icon,
                            'description' => $child->description,
                            'tooltip' => $child->tooltip,
                            'order' => $child->order,
                            'is_active' => $child->is_active,
                            'metadata' => $child->metadata,
                        ];
                    }),
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'All menu items retrieved successfully',
                'data' => $menuItems
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve menu items',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
