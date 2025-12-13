<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SystemConfiguration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SystemConfigurationController extends Controller
{
    /**
     * Get all system configurations grouped by category
     */
    public function index()
    {
        try {
            $configurations = SystemConfiguration::getAllGrouped();

            return response()->json([
                'success' => true,
                'data' => $configurations,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve configurations',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get configurations by category
     */
    public function getByCategory($category)
    {
        try {
            $configurations = SystemConfiguration::byCategory($category)
                ->active()
                ->get()
                ->map(function ($config) {
                    return [
                        'id' => $config->id,
                        'key' => $config->key,
                        'value' => $config->is_encrypted ? '********' : $config->value,
                        'type' => $config->type,
                        'description' => $config->description,
                        'is_encrypted' => $config->is_encrypted,
                        'is_active' => $config->is_active,
                        'metadata' => $config->metadata,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $configurations,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve configurations',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get a specific configuration
     */
    public function show($id)
    {
        try {
            $config = SystemConfiguration::findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $config->id,
                    'category' => $config->category,
                    'key' => $config->key,
                    'value' => $config->is_encrypted ? '********' : $config->value,
                    'type' => $config->type,
                    'description' => $config->description,
                    'is_encrypted' => $config->is_encrypted,
                    'is_active' => $config->is_active,
                    'metadata' => $config->metadata,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Configuration not found',
                'error' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Update a configuration
     */
    public function update(Request $request, $id)
    {
        try {
            $config = SystemConfiguration::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'value' => 'required',
                'is_active' => 'sometimes|boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $config->value = $request->value;

            if ($request->has('is_active')) {
                $config->is_active = $request->is_active;
            }

            $config->save();

            return response()->json([
                'success' => true,
                'message' => 'Configuration updated successfully',
                'data' => $config,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update configuration',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Bulk update configurations
     */
    public function bulkUpdate(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'configurations' => 'required|array',
                'configurations.*.key' => 'required|string',
                'configurations.*.value' => 'required',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $updated = [];

            foreach ($request->configurations as $configData) {
                $config = SystemConfiguration::where('key', $configData['key'])->first();

                if ($config) {
                    $config->value = $configData['value'];
                    $config->save();
                    $updated[] = $config->key;
                }
            }

            // Clear all cache after bulk update
            SystemConfiguration::clearAllCache();

            return response()->json([
                'success' => true,
                'message' => 'Configurations updated successfully',
                'data' => [
                    'updated_keys' => $updated,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update configurations',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Test mail configuration
     */
    public function testMailConfig(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'test_email' => 'required|email',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            // Get mail configurations from database
            $mailConfig = SystemConfiguration::getMailConfig();

            // Configure mail settings dynamically
            config([
                'mail.mailers.smtp.host' => $mailConfig['mail.host'] ?? 'smtp.gmail.com',
                'mail.mailers.smtp.port' => $mailConfig['mail.port'] ?? 465,
                'mail.mailers.smtp.encryption' => $mailConfig['mail.encryption'] ?? 'ssl',
                'mail.mailers.smtp.username' => $mailConfig['mail.username'] ?? '',
                'mail.mailers.smtp.password' => SystemConfiguration::get('mail.password', ''),
                'mail.from.address' => $mailConfig['mail.from.address'] ?? '',
                'mail.from.name' => $mailConfig['mail.from.name'] ?? 'JKM CRM',
            ]);

            // Send test email
            \Illuminate\Support\Facades\Mail::raw(
                'This is a test email from JKM CRM System Configuration.',
                function ($message) use ($request) {
                    $message->to($request->test_email)
                        ->subject('Test Email - JKM CRM');
                }
            );

            return response()->json([
                'success' => true,
                'message' => 'Test email sent successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send test email',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Clear configuration cache
     */
    public function clearCache()
    {
        try {
            SystemConfiguration::clearAllCache();

            return response()->json([
                'success' => true,
                'message' => 'Configuration cache cleared successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to clear cache',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Test LDAP connection
     */
    public function testLdapConnection()
    {
        try {
            $result = \App\Services\AuthenticationService::testLdapConnection();

            return response()->json([
                'success' => $result['success'],
                'message' => $result['message'],
            ], $result['success'] ? 200 : 500);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to test LDAP connection',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
