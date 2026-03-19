<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CategoryController extends Controller
{
    /**
     * Display a listing of categories.
     */
    public function index(Request $request)
    {
        $query = Category::with(['parent', 'children']);

        // Filter by department
        if ($request->has('department')) {
            $query->byDepartment($request->department);
        }

        // Filter by level
        if ($request->has('level')) {
            $query->byLevel($request->level);
        }

        // Filter by active status
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Get only root categories if requested
        if ($request->boolean('roots_only')) {
            $query->roots();
        }

        // Search by name
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('name_ms', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        $perPage = $request->get('per_page', 50);
        $sortBy = in_array($request->get('sort_by'), ['name', 'sort_order', 'created_at']) ? $request->get('sort_by') : 'sort_order';
        $categories = $query->orderBy($sortBy)->paginate($perPage);

        return response()->json($categories);
    }

    /**
     * Get category tree structure.
     */
    public function tree(Request $request)
    {
        $department = $request->get('department');
        $tree = Category::getTree($department);

        return response()->json([
            'success' => true,
            'data' => $tree
        ]);
    }

    /**
     * Store a newly created category.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'name_ms' => 'nullable|string|max:255',
            'parent_id' => 'nullable|exists:categories,id',
            'department' => 'required|string|max:255',
            'level' => 'required|integer|min:1|max:4',
            'code' => 'nullable|string|max:50|unique:categories,code',
            'description' => 'nullable|string',
            'description_ms' => 'nullable|string',
            'is_active' => 'boolean',
            'sort_order' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $category = Category::create($request->all());
        $category->load(['parent', 'children']);

        return response()->json([
            'success' => true,
            'message' => 'Category created successfully',
            'data' => $category
        ], 201);
    }

    /**
     * Display the specified category.
     */
    public function show($id)
    {
        $category = Category::with(['parent', 'children', 'descendants'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $category
        ]);
    }

    /**
     * Update the specified category.
     */
    public function update(Request $request, $id)
    {
        $category = Category::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'string|max:255',
            'name_ms' => 'nullable|string|max:255',
            'parent_id' => 'nullable|exists:categories,id',
            'department' => 'string|max:255',
            'level' => 'integer|min:1|max:4',
            'code' => 'nullable|string|max:50|unique:categories,code,' . $id,
            'description' => 'nullable|string',
            'description_ms' => 'nullable|string',
            'is_active' => 'boolean',
            'sort_order' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Prevent circular parent relationship
        if ($request->has('parent_id') && $request->parent_id) {
            $parent = Category::find($request->parent_id);
            if ($parent && $parent->ancestors()->contains($category->id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot set category as its own ancestor'
                ], 422);
            }
        }

        $category->update($request->all());
        $category->load(['parent', 'children']);

        return response()->json([
            'success' => true,
            'message' => 'Category updated successfully',
            'data' => $category
        ]);
    }

    /**
     * Remove the specified category.
     */
    public function destroy($id)
    {
        $category = Category::findOrFail($id);

        // Check if category has children
        if ($category->children()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete category with subcategories'
            ], 422);
        }

        $category->delete();

        return response()->json([
            'success' => true,
            'message' => 'Category deleted successfully'
        ]);
    }

    /**
     * Get categories by department and level.
     */
    public function byDepartmentLevel(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'department' => 'required|string',
            'level' => 'required|integer|min:1|max:4',
            'parent_id' => 'nullable|exists:categories,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $query = Category::active()
            ->byDepartment($request->department)
            ->byLevel($request->level);

        if ($request->has('parent_id')) {
            $query->where('parent_id', $request->parent_id);
        }

        $categories = $query->orderBy('sort_order')->get();

        return response()->json([
            'success' => true,
            'data' => $categories
        ]);
    }
}
