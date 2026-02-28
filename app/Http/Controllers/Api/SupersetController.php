<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SupersetDashboard;
use App\Models\SupersetConfiguration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SupersetController extends Controller
{
    /**
     * Superset configuration
     */
    private function getSupersetConfig()
    {
        return [
            'url' => SupersetConfiguration::getValue('SUPERSET_URL', env('SUPERSET_URL', 'http://localhost:8088')),
            'username' => SupersetConfiguration::getValue('SUPERSET_USERNAME', env('SUPERSET_USERNAME', 'admin')),
            'password' => SupersetConfiguration::getValue('SUPERSET_PASSWORD', env('SUPERSET_PASSWORD', 'admin')),
        ];
    }

    /**
     * Get access token and CSRF token from Superset
     *
     * @return array|null ['access_token' => string, 'csrf_token' => string]
     */
    private function getSupersetTokens()
    {
        $config = $this->getSupersetConfig();

        try {
            // First, login to get access token
            $loginResponse = Http::post("{$config['url']}/api/v1/security/login", [
                'username' => $config['username'],
                'password' => $config['password'],
                'provider' => 'db',
                'refresh' => true,
            ]);

            if (!$loginResponse->successful()) {
                Log::error('Failed to login to Superset', [
                    'status' => $loginResponse->status(),
                    'body' => $loginResponse->body(),
                ]);
                return null;
            }

            $accessToken = $loginResponse->json()['access_token'] ?? null;

            if (!$accessToken) {
                Log::error('No access token in Superset login response');
                return null;
            }

            // Then, get CSRF token using the access token
            $csrfResponse = Http::withHeaders([
                'Authorization' => "Bearer {$accessToken}",
            ])->get("{$config['url']}/api/v1/security/csrf_token/");

            if (!$csrfResponse->successful()) {
                Log::error('Failed to get CSRF token from Superset', [
                    'status' => $csrfResponse->status(),
                    'body' => $csrfResponse->body(),
                ]);
                return null;
            }

            $csrfToken = $csrfResponse->json()['result'] ?? null;

            return [
                'access_token' => $accessToken,
                'csrf_token' => $csrfToken,
            ];

        } catch (\Exception $e) {
            Log::error('Exception while getting Superset tokens', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Get access token from Superset (for backward compatibility)
     *
     * @return string|null
     */
    private function getSupersetAccessToken()
    {
        $tokens = $this->getSupersetTokens();
        return $tokens['access_token'] ?? null;
    }

    /**
     * Generate a guest token for embedding
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getGuestToken(Request $request)
    {
        $request->validate([
            'dashboard_id' => 'required|string',
            'rls' => 'sometimes|array',
        ]);

        $user = $request->user();

        // Check if user has access to the requested dashboard
        $dashboard = SupersetDashboard::where('superset_id', $request->dashboard_id)->first();

        if ($dashboard) {
            // Check role-based access
            if (!$dashboard->is_public && $user) {
                if (!$user->role_id || !$dashboard->hasRoleAccess($user->role_id)) {
                    return response()->json([
                        'error' => 'Access denied to this dashboard'
                    ], 403);
                }
            }
        }

        $config = $this->getSupersetConfig();
        $tokens = $this->getSupersetTokens();

        if (!$tokens || !$tokens['access_token']) {
            return response()->json([
                'error' => 'Failed to authenticate with Superset'
            ], 500);
        }

        $accessToken = $tokens['access_token'];
        $csrfToken = $tokens['csrf_token'];

        try {
            // Get user info
            $username = $user ? $user->email : 'guest';
            $firstName = $user ? $user->name : 'Guest';
            $lastName = 'User';

            // Build RLS rules
            $rls = $request->input('rls', []);

            // Apply dashboard-specific RLS configuration
            if ($dashboard && $dashboard->rls_config) {
                $rls = array_merge($rls, $dashboard->rls_config);
            }

            // Apply role-specific custom RLS if exists
            if ($dashboard && $user && $user->role_id) {
                $permissions = $dashboard->getRolePermissions($user->role_id);
                if ($permissions && $permissions['custom_rls']) {
                    $rls = array_merge($rls, $permissions['custom_rls']);
                }
            }

            // Add custom RLS rules based on the authenticated user
            // Example: Filter data by user's department, organization, etc.
            // if ($user && $user->department_id) {
            //     $rls[] = [
            //         'dataset' => 1,  // Your dataset ID
            //         'clause' => "department_id = {$user->department_id}"
            //     ];
            // }

            $headers = [
                'Authorization' => "Bearer {$accessToken}",
                'Content-Type' => 'application/json',
            ];

            // Add CSRF token if available
            if ($csrfToken) {
                $headers['X-CSRFToken'] = $csrfToken;
            }

            $response = Http::withHeaders($headers)->post("{$config['url']}/api/v1/security/guest_token/", [
                'resources' => [
                    [
                        'type' => 'dashboard',
                        'id' => $request->input('dashboard_id'),
                    ]
                ],
                'rls' => $rls,
                'user' => [
                    'username' => $username,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                ]
            ]);

            if ($response->successful()) {
                return response()->json([
                    'token' => $response->json()['token']
                ]);
            }

            Log::error('Failed to get guest token from Superset', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return response()->json([
                'error' => 'Failed to generate guest token',
                'details' => $response->json()
            ], $response->status());

        } catch (\Exception $e) {
            Log::error('Exception while getting guest token', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'An error occurred while generating the guest token',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get list of available dashboards (optional helper)
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getDashboards()
    {
        $config = $this->getSupersetConfig();
        $accessToken = $this->getSupersetAccessToken();

        if (!$accessToken) {
            return response()->json([
                'error' => 'Failed to authenticate with Superset'
            ], 500);
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$accessToken}",
            ])->get("{$config['url']}/api/v1/dashboard/");

            if ($response->successful()) {
                $dashboards = collect($response->json()['result'])->map(function ($dashboard) {
                    return [
                        'id' => $dashboard['id'],
                        'uuid' => $dashboard['id'],
                        'dashboard_title' => $dashboard['dashboard_title'],
                        'url' => $dashboard['url'],
                        'published' => $dashboard['published'],
                    ];
                });

                return response()->json([
                    'dashboards' => $dashboards
                ]);
            }

            return response()->json([
                'error' => 'Failed to fetch dashboards',
                'details' => $response->json()
            ], $response->status());

        } catch (\Exception $e) {
            Log::error('Exception while fetching dashboards', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'An error occurred while fetching dashboards',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
