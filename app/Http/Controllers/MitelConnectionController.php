<?php

namespace App\Http\Controllers;

use App\Models\MitelConnection;
use App\Models\MitelSyncLog;
use App\Services\MitelDataSyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MitelConnectionController extends Controller
{
    /**
     * Get all Mitel connections
     */
    public function index()
    {
        $connections = MitelConnection::with('latestSyncLog')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($connection) {
                return [
                    'id' => $connection->id,
                    'name' => $connection->name,
                    'description' => $connection->description,
                    'host' => $connection->host,
                    'port' => $connection->port,
                    'database' => $connection->database,
                    'username' => $connection->username,
                    'is_active' => $connection->is_active,
                    'sync_status' => $connection->sync_status,
                    'last_sync_at' => $connection->last_sync_at,
                    'last_sync_message' => $connection->last_sync_message,
                    'created_at' => $connection->created_at,
                    'updated_at' => $connection->updated_at,
                ];
            });

        return response()->json([
            'status' => 'success',
            'data' => $connections,
        ]);
    }

    /**
     * Get a specific connection
     */
    public function show($id)
    {
        $connection = MitelConnection::with('latestSyncLog')->find($id);

        if (!$connection) {
            return response()->json([
                'status' => 'error',
                'message' => 'Connection not found',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'id' => $connection->id,
                'name' => $connection->name,
                'description' => $connection->description,
                'host' => $connection->host,
                'port' => $connection->port,
                'database' => $connection->database,
                'username' => $connection->username,
                'is_active' => $connection->is_active,
                'sync_status' => $connection->sync_status,
                'last_sync_at' => $connection->last_sync_at,
                'last_sync_message' => $connection->last_sync_message,
                'created_at' => $connection->created_at,
                'updated_at' => $connection->updated_at,
            ],
        ]);
    }

    /**
     * Create a new Mitel connection
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:mitel_connections,name',
            'description' => 'nullable|string',
            'host' => 'required|string|max:255',
            'port' => 'required|integer|min:1|max:65535',
            'database' => 'required|string|max:255',
            'username' => 'required|string|max:255',
            'password' => 'required|string',
            'encrypt' => 'boolean',
            'trust_server_certificate' => 'boolean',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $connection = MitelConnection::create($request->all());

        return response()->json([
            'status' => 'success',
            'message' => 'Connection created successfully',
            'data' => $connection,
        ], 201);
    }

    /**
     * Update an existing connection
     */
    public function update(Request $request, $id)
    {
        $connection = MitelConnection::find($id);

        if (!$connection) {
            return response()->json([
                'status' => 'error',
                'message' => 'Connection not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255|unique:mitel_connections,name,' . $id,
            'description' => 'nullable|string',
            'host' => 'sometimes|required|string|max:255',
            'port' => 'sometimes|required|integer|min:1|max:65535',
            'database' => 'sometimes|required|string|max:255',
            'username' => 'sometimes|required|string|max:255',
            'password' => 'sometimes|required|string',
            'encrypt' => 'boolean',
            'trust_server_certificate' => 'boolean',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $connection->update($request->all());

        return response()->json([
            'status' => 'success',
            'message' => 'Connection updated successfully',
            'data' => $connection,
        ]);
    }

    /**
     * Delete a connection
     */
    public function destroy($id)
    {
        $connection = MitelConnection::find($id);

        if (!$connection) {
            return response()->json([
                'status' => 'error',
                'message' => 'Connection not found',
            ], 404);
        }

        $connection->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Connection deleted successfully',
        ]);
    }

    /**
     * Test a connection
     */
    public function test($id)
    {
        $connection = MitelConnection::find($id);

        if (!$connection) {
            return response()->json([
                'status' => 'error',
                'message' => 'Connection not found',
            ], 404);
        }

        try {
            $syncService = new MitelDataSyncService($connection);
            $result = $syncService->testConnection();

            return response()->json([
                'status' => $result['success'] ? 'success' : 'error',
                'message' => $result['message'],
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Connection test failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Trigger manual sync for a connection
     */
    public function sync(Request $request, $id)
    {
        $connection = MitelConnection::find($id);

        if (!$connection) {
            return response()->json([
                'status' => 'error',
                'message' => 'Connection not found',
            ], 404);
        }

        if (!$connection->is_active) {
            return response()->json([
                'status' => 'error',
                'message' => 'Connection is not active',
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'sync_type' => 'sometimes|in:full,incremental',
            'tables' => 'sometimes|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $syncService = new MitelDataSyncService($connection);
            $tables = $request->input('tables', []);
            $syncType = $request->input('sync_type', 'incremental');

            // Run sync in background
            dispatch(function () use ($syncService, $tables, $syncType) {
                $syncService->sync($tables, $syncType);
            })->afterResponse();

            return response()->json([
                'status' => 'success',
                'message' => 'Sync started successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to start sync: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get sync logs for a connection
     */
    public function logs($id, Request $request)
    {
        $connection = MitelConnection::find($id);

        if (!$connection) {
            return response()->json([
                'status' => 'error',
                'message' => 'Connection not found',
            ], 404);
        }

        $perPage = $request->input('per_page', 15);
        $logs = MitelSyncLog::where('mitel_connection_id', $id)
            ->orderBy('started_at', 'desc')
            ->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data' => $logs,
        ]);
    }

    /**
     * Get sync statistics for a connection
     */
    public function stats($id)
    {
        $connection = MitelConnection::find($id);

        if (!$connection) {
            return response()->json([
                'status' => 'error',
                'message' => 'Connection not found',
            ], 404);
        }

        $stats = [
            'total_syncs' => MitelSyncLog::where('mitel_connection_id', $id)->count(),
            'successful_syncs' => MitelSyncLog::where('mitel_connection_id', $id)
                ->where('status', 'success')
                ->count(),
            'failed_syncs' => MitelSyncLog::where('mitel_connection_id', $id)
                ->where('status', 'failed')
                ->count(),
            'total_records_fetched' => MitelSyncLog::where('mitel_connection_id', $id)
                ->sum('records_fetched'),
            'total_records_inserted' => MitelSyncLog::where('mitel_connection_id', $id)
                ->sum('records_inserted'),
            'total_records_updated' => MitelSyncLog::where('mitel_connection_id', $id)
                ->sum('records_updated'),
            'last_successful_sync' => MitelSyncLog::where('mitel_connection_id', $id)
                ->where('status', 'success')
                ->orderBy('completed_at', 'desc')
                ->first(),
        ];

        return response()->json([
            'status' => 'success',
            'data' => $stats,
        ]);
    }
}
