<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EmailConfiguration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class EmailConfigurationController extends Controller
{
    /**
     * Display a listing of email configurations.
     */
    public function index(Request $request)
    {
        $query = EmailConfiguration::query();

        // Filter by type
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        // Filter by active status
        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Sort
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $perPage = $request->get('per_page', 15);
        $configs = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $configs,
        ]);
    }

    /**
     * Store a newly created email configuration.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'type' => 'required|in:smtp,imap,both',
            'is_active' => 'boolean',
            'is_default' => 'boolean',

            // SMTP fields
            'smtp_host' => 'required_if:type,smtp,both|nullable|string',
            'smtp_port' => 'required_if:type,smtp,both|nullable|integer',
            'smtp_encryption' => 'nullable|in:ssl,tls',
            'smtp_username' => 'required_if:type,smtp,both|nullable|string',
            'smtp_password' => 'required_if:type,smtp,both|nullable|string',
            'smtp_from_address' => 'nullable|email',
            'smtp_from_name' => 'nullable|string',

            // IMAP fields
            'imap_host' => 'required_if:type,imap,both|nullable|string',
            'imap_port' => 'required_if:type,imap,both|nullable|integer',
            'imap_encryption' => 'nullable|in:ssl,tls',
            'imap_username' => 'required_if:type,imap,both|nullable|string',
            'imap_password' => 'required_if:type,imap,both|nullable|string',
            'imap_folder' => 'nullable|string',

            // Additional settings
            'department' => 'nullable|string',
            'auto_assign' => 'boolean',
            'default_priority' => 'nullable|in:P1,P2,P3,P4',
            'settings' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $config = EmailConfiguration::create($validator->validated());

            return response()->json([
                'success' => true,
                'message' => 'Email configuration created successfully',
                'data' => $config,
            ], 201);
        } catch (\Exception $e) {
            Log::error('Failed to create email configuration: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to create email configuration',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified email configuration.
     */
    public function show(string $id)
    {
        $config = EmailConfiguration::find($id);

        if (!$config) {
            return response()->json([
                'success' => false,
                'message' => 'Email configuration not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $config,
        ]);
    }

    /**
     * Update the specified email configuration.
     */
    public function update(Request $request, string $id)
    {
        $config = EmailConfiguration::find($id);

        if (!$config) {
            return response()->json([
                'success' => false,
                'message' => 'Email configuration not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'type' => 'sometimes|in:smtp,imap,both',
            'is_active' => 'boolean',
            'is_default' => 'boolean',

            // SMTP fields
            'smtp_host' => 'nullable|string',
            'smtp_port' => 'nullable|integer',
            'smtp_encryption' => 'nullable|in:ssl,tls',
            'smtp_username' => 'nullable|string',
            'smtp_password' => 'nullable|string',
            'smtp_from_address' => 'nullable|email',
            'smtp_from_name' => 'nullable|string',

            // IMAP fields
            'imap_host' => 'nullable|string',
            'imap_port' => 'nullable|integer',
            'imap_encryption' => 'nullable|in:ssl,tls',
            'imap_username' => 'nullable|string',
            'imap_password' => 'nullable|string',
            'imap_folder' => 'nullable|string',

            // Additional settings
            'department' => 'nullable|string',
            'auto_assign' => 'boolean',
            'default_priority' => 'nullable|in:P1,P2,P3,P4',
            'settings' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $data = $validator->validated();

            // Don't update password if it's not provided
            if (isset($data['smtp_password']) && empty($data['smtp_password'])) {
                unset($data['smtp_password']);
            }
            if (isset($data['imap_password']) && empty($data['imap_password'])) {
                unset($data['imap_password']);
            }

            $config->update($data);

            return response()->json([
                'success' => true,
                'message' => 'Email configuration updated successfully',
                'data' => $config->fresh(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update email configuration: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to update email configuration',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified email configuration.
     */
    public function destroy(string $id)
    {
        $config = EmailConfiguration::find($id);

        if (!$config) {
            return response()->json([
                'success' => false,
                'message' => 'Email configuration not found',
            ], 404);
        }

        try {
            $config->delete();

            return response()->json([
                'success' => true,
                'message' => 'Email configuration deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete email configuration',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Test SMTP connection.
     */
    public function testSmtp(string $id)
    {
        $config = EmailConfiguration::find($id);

        if (!$config) {
            return response()->json([
                'success' => false,
                'message' => 'Email configuration not found',
            ], 404);
        }

        if (!in_array($config->type, ['smtp', 'both'])) {
            return response()->json([
                'success' => false,
                'message' => 'This configuration is not configured for SMTP',
            ], 400);
        }

        try {
            $result = $config->testSmtpConnection();

            return response()->json([
                'success' => $result['success'],
                'message' => $result['message'],
            ], $result['success'] ? 200 : 400);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'SMTP test failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Test IMAP connection.
     */
    public function testImap(string $id)
    {
        $config = EmailConfiguration::find($id);

        if (!$config) {
            return response()->json([
                'success' => false,
                'message' => 'Email configuration not found',
            ], 404);
        }

        if (!in_array($config->type, ['imap', 'both'])) {
            return response()->json([
                'success' => false,
                'message' => 'This configuration is not configured for IMAP',
            ], 400);
        }

        try {
            $result = $config->testImapConnection();

            return response()->json([
                'success' => $result['success'],
                'message' => $result['message'],
            ], $result['success'] ? 200 : 400);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'IMAP test failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Set as default configuration.
     */
    public function setDefault(string $id)
    {
        $config = EmailConfiguration::find($id);

        if (!$config) {
            return response()->json([
                'success' => false,
                'message' => 'Email configuration not found',
            ], 404);
        }

        try {
            $config->is_default = true;
            $config->save();

            return response()->json([
                'success' => true,
                'message' => 'Configuration set as default successfully',
                'data' => $config,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to set as default',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Toggle active status.
     */
    public function toggleActive(string $id)
    {
        $config = EmailConfiguration::find($id);

        if (!$config) {
            return response()->json([
                'success' => false,
                'message' => 'Email configuration not found',
            ], 404);
        }

        try {
            $config->is_active = !$config->is_active;
            $config->save();

            return response()->json([
                'success' => true,
                'message' => 'Configuration status updated successfully',
                'data' => $config,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to toggle active status',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get active IMAP configurations.
     */
    public function getActiveImap()
    {
        try {
            $configs = EmailConfiguration::getActiveImapConfigs();

            return response()->json([
                'success' => true,
                'data' => $configs,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch IMAP configurations',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
