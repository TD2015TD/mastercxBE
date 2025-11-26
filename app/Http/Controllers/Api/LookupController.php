<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lookup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class LookupController extends Controller
{
    /**
     * Get all lookups grouped by type.
     */
    public function index(Request $request)
    {
        $locale = $request->get('locale', 'en');
        
        if ($request->has('type')) {
            // Get lookups for specific type
            $lookups = Lookup::ofType($request->type)
                ->active()
                ->orderBy('sort_order')
                ->get()
                ->map(function ($item) use ($locale) {
                    return [
                        'id' => $item->id,
                        'code' => $item->code,
                        'name' => $locale === 'ms' ? $item->name_ms : $item->name_en,
                        'description' => $locale === 'ms' ? $item->description_ms : $item->description_en,
                        'color' => $item->color,
                        'icon' => $item->icon,
                        'metadata' => $item->metadata,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $lookups
            ]);
        }

        // Get all lookups grouped by type
        $groupedLookups = Lookup::getAllGrouped($locale);

        return response()->json([
            'success' => true,
            'data' => $groupedLookups
        ]);
    }

    /**
     * Get available lookup types.
     */
    public function types()
    {
        $types = Lookup::select('type')
            ->distinct()
            ->orderBy('type')
            ->pluck('type');

        return response()->json([
            'success' => true,
            'data' => $types
        ]);
    }

    /**
     * Store a newly created lookup.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type' => 'required|string|max:255',
            'code' => 'required|string|max:255',
            'name_en' => 'required|string|max:255',
            'name_ms' => 'required|string|max:255',
            'description_en' => 'nullable|string',
            'description_ms' => 'nullable|string',
            'sort_order' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
            'color' => 'nullable|string|max:50',
            'icon' => 'nullable|string|max:50',
            'metadata' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Check for duplicate type + code
        $existing = Lookup::where('type', $request->type)
            ->where('code', $request->code)
            ->first();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'Lookup with this type and code already exists'
            ], 422);
        }

        $lookup = Lookup::create($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Lookup created successfully',
            'data' => $lookup
        ], 201);
    }

    /**
     * Display the specified lookup.
     */
    public function show($id, Request $request)
    {
        $lookup = Lookup::find($id);

        if (!$lookup) {
            return response()->json([
                'success' => false,
                'message' => 'Lookup not found'
            ], 404);
        }

        $locale = $request->get('locale', 'en');

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $lookup->id,
                'type' => $lookup->type,
                'code' => $lookup->code,
                'name' => $locale === 'ms' ? $lookup->name_ms : $lookup->name_en,
                'name_en' => $lookup->name_en,
                'name_ms' => $lookup->name_ms,
                'description' => $locale === 'ms' ? $lookup->description_ms : $lookup->description_en,
                'description_en' => $lookup->description_en,
                'description_ms' => $lookup->description_ms,
                'sort_order' => $lookup->sort_order,
                'is_active' => $lookup->is_active,
                'color' => $lookup->color,
                'icon' => $lookup->icon,
                'metadata' => $lookup->metadata,
            ]
        ]);
    }

    /**
     * Update the specified lookup.
     */
    public function update(Request $request, $id)
    {
        $lookup = Lookup::find($id);

        if (!$lookup) {
            return response()->json([
                'success' => false,
                'message' => 'Lookup not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'type' => 'sometimes|required|string|max:255',
            'code' => 'sometimes|required|string|max:255',
            'name_en' => 'sometimes|required|string|max:255',
            'name_ms' => 'sometimes|required|string|max:255',
            'description_en' => 'nullable|string',
            'description_ms' => 'nullable|string',
            'sort_order' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
            'color' => 'nullable|string|max:50',
            'icon' => 'nullable|string|max:50',
            'metadata' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Check for duplicate type + code (excluding current record)
        if ($request->has('type') || $request->has('code')) {
            $type = $request->get('type', $lookup->type);
            $code = $request->get('code', $lookup->code);

            $existing = Lookup::where('type', $type)
                ->where('code', $code)
                ->where('id', '!=', $id)
                ->first();

            if ($existing) {
                return response()->json([
                    'success' => false,
                    'message' => 'Lookup with this type and code already exists'
                ], 422);
            }
        }

        $lookup->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Lookup updated successfully',
            'data' => $lookup
        ]);
    }

    /**
     * Remove the specified lookup.
     */
    public function destroy($id)
    {
        $lookup = Lookup::find($id);

        if (!$lookup) {
            return response()->json([
                'success' => false,
                'message' => 'Lookup not found'
            ], 404);
        }

        $lookup->delete();

        return response()->json([
            'success' => true,
            'message' => 'Lookup deleted successfully'
        ]);
    }

    /**
     * Bulk create or update lookups.
     */
    public function bulkUpsert(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'lookups' => 'required|array',
            'lookups.*.type' => 'required|string',
            'lookups.*.code' => 'required|string',
            'lookups.*.name_en' => 'required|string',
            'lookups.*.name_ms' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $created = 0;
        $updated = 0;

        foreach ($request->lookups as $lookupData) {
            $lookup = Lookup::updateOrCreate(
                [
                    'type' => $lookupData['type'],
                    'code' => $lookupData['code']
                ],
                $lookupData
            );

            if ($lookup->wasRecentlyCreated) {
                $created++;
            } else {
                $updated++;
            }
        }

        return response()->json([
            'success' => true,
            'message' => "Successfully processed {$created} created and {$updated} updated lookups",
            'summary' => [
                'created' => $created,
                'updated' => $updated,
                'total' => $created + $updated
            ]
        ]);
    }
}
