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
     * Export a Superset dashboard as PDF (proxied through Laravel so we can
     * inject admin credentials without exposing them to the browser).
     *
     * POST /api/v1/superset/export-pdf
     * Body: { "dashboard_id": "<superset uuid>" }
     *
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\StreamedResponse|\Illuminate\Http\JsonResponse
     */
    public function exportPdf(Request $request)
    {
        $request->validate([
            'dashboard_id' => 'required|string',
        ]);

        $dashboardUuid = $request->input('dashboard_id');

        // Access check: only let users who have role access proceed
        $user       = $request->user();
        $dashboard  = SupersetDashboard::where('superset_id', $dashboardUuid)->first();

        if ($dashboard && !$dashboard->is_public && $user) {
            if (!$user->role_id || !$dashboard->hasRoleAccess($user->role_id)) {
                return response()->json(['error' => 'Access denied to this dashboard'], 403);
            }
        }

        $config      = $this->getSupersetConfig();
        $accessToken = $this->getSupersetAccessToken();

        if (!$accessToken) {
            return response()->json(['error' => 'Failed to authenticate with Superset'], 500);
        }

        try {
            // Superset async PDF endpoint — returns a task key on newer versions.
            // For versions that support sync PDF:
            // GET /api/v1/dashboard/{pk}/export/ (returns JSON)
            // For PDF screenshot:
            // POST /api/v1/report/                 (creates a one-off report)
            // Fallback: use the screenshot API to get a PNG per chart.
            //
            // Broadest-compatible approach: use the /api/v1/dashboard/<id>/screenshot/
            // which returns a PNG, or trigger the printable page URL.

            // 1. Resolve numeric PK from UUID
            $dashboardsResp = Http::withHeaders([
                'Authorization' => "Bearer {$accessToken}",
            ])->get("{$config['url']}/api/v1/dashboard/?q=(filters:!((col:uuid,opr:DashboardUUIDFilter,val:{$dashboardUuid})))");

            if (!$dashboardsResp->successful()) {
                return response()->json(['error' => 'Dashboard not found in Superset'], 404);
            }

            $result = $dashboardsResp->json()['result'] ?? [];
            if (empty($result)) {
                return response()->json(['error' => 'Dashboard not found in Superset'], 404);
            }

            $pk = $result[0]['id'];

            // 2. Request PDF export
            $pdfResp = Http::withHeaders([
                'Authorization' => "Bearer {$accessToken}",
                'Accept'        => 'application/pdf',
            ])->get("{$config['url']}/api/v1/dashboard/{$pk}/export/");

            if ($pdfResp->successful()) {
                $contentType = $pdfResp->header('Content-Type');

                // If Superset returned a real PDF/binary stream, proxy it
                if (str_contains($contentType, 'pdf') || str_contains($contentType, 'octet-stream')) {
                    return response($pdfResp->body(), 200, [
                        'Content-Type'        => 'application/pdf',
                        'Content-Disposition' => 'attachment; filename="dashboard-report.pdf"',
                    ]);
                }

                // Some Superset versions return JSON (dashboard definition export), not PDF
                // Return a redirect to the printable URL instead
            }

            // 3. Fallback: return the Superset printable URL so the frontend
            //    can open it in a new tab (user downloads from Superset directly)
            $printUrl = "{$config['url']}/superset/dashboard/{$pk}/?standalone=3";

            return response()->json([
                'success'   => true,
                'method'    => 'redirect',
                'print_url' => $printUrl,
                'message'   => 'Open this URL in your browser to download the PDF.',
            ]);

        } catch (\Exception $e) {
            Log::error('Exception during Superset PDF export', ['error' => $e->getMessage()]);
            return response()->json([
                'error'   => 'Failed to generate report',
                'message' => $e->getMessage(),
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
