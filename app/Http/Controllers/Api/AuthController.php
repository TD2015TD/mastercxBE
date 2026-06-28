<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use App\Models\Ticket;
use App\Models\User;
use App\Services\AuthenticationService;
use App\Services\McxOpenMediaService;
use Tymon\JWTAuth\Facades\JWTAuth;
use OpenApi\Attributes as OA;

class AuthController extends Controller
{
    /**
     * Authenticate user via username/password or Ignite Mitel and return JWT token
     */
    #[OA\Post(
        path: "/api/v1/auth/login",
        summary: "User login",
        description: "Authenticate user with username/password or Ignite token and return JWT access token",
        tags: ["Authentication"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["username", "password"],
                properties: [
                    new OA\Property(property: "username", type: "string", example: "superadmin@jkm.gov.my"),
                    new OA\Property(property: "password", type: "string", format: "password", example: "password"),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Successful authentication",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(
                            property: "data",
                            properties: [
                                new OA\Property(
                                    property: "user",
                                    properties: [
                                        new OA\Property(property: "id", type: "integer", example: 1),
                                        new OA\Property(property: "name", type: "string", example: "Super Administrator"),
                                        new OA\Property(property: "email", type: "string", example: "superadmin@jkm.gov.my"),
                                        new OA\Property(property: "role_id", type: "integer", example: 1),
                                        new OA\Property(
                                            property: "role",
                                            properties: [
                                                new OA\Property(property: "id", type: "integer", example: 1),
                                                new OA\Property(property: "name", type: "string", example: "Super Admin"),
                                                new OA\Property(property: "slug", type: "string", example: "super_admin"),
                                            ],
                                            type: "object"
                                        ),
                                    ],
                                    type: "object"
                                ),
                                new OA\Property(property: "access_token", type: "string", example: "eyJ0eXAiOiJKV1QiLCJhbGc..."),
                                new OA\Property(property: "token_type", type: "string", example: "bearer"),
                                new OA\Property(property: "expires_in", type: "integer", example: 3600),
                            ],
                            type: "object"
                        ),
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: "Invalid credentials",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: false),
                        new OA\Property(property: "message", type: "string", example: "Invalid credentials"),
                    ]
                )
            ),
            new OA\Response(
                response: 422,
                description: "Validation error",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: false),
                        new OA\Property(property: "errors", type: "object"),
                    ]
                )
            ),
        ]
    )]
    public function login(Request $request)
    {
        Log::info('Login attempt initiated', [
            'has_username' => $request->has('username'),
            'has_password' => $request->has('password'),
            'has_ignite_token' => $request->has('ignite_token'),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent()
        ]);

        // Check if it's username/password login or Ignite token login
        if ($request->has('username') && $request->has('password')) {
            $response = $this->usernamePasswordLogin($request);
        } elseif ($request->has('ignite_token')) {
            $response = $this->igniteTokenLogin($request);
        } else {
            Log::warning('Login attempt with missing credentials', [
                'ip_address' => $request->ip()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Please provide either username/password or ignite_token'
            ], 422);
        }

        // If login is successful, add MitelToken to the user attribute if agent_username exists
        if ($response->getStatusCode() === 200 && $response->getData()->success) {
            $user = $response->getData()->data->user;

            if ($user->agent && !empty($user->agent->agent_username)) {
                try {
                    $mitelService = app(\App\Services\MitelService::class);
                    $mitelToken = $mitelService->authenticate($user->agent->agent_username);

                    $user->MitelToken = $mitelToken;
                    Log::info('MitelToken retrieved successfully', [
                        'user_id' => $user->id,
                        'agent_username' => $user->agent->agent_username
                    ]);
                    
                    // Get permissions from original response
                    $originalData = $response->getData()->data;
                    $permissions = $originalData->permissions ?? [];
                    
                    Log::info('Returning Mitel agent login response', [
                        'user_id' => $user->id,
                        'has_agent' => !!($originalData->agent ?? null),
                        'permissions_count' => count($permissions),
                        'permissions' => $permissions
                    ]);
                    
                    return response()->json([
                        'success' => true,
                        'data' => [
                            'user' => $user,
                            'agent' => $originalData->agent ?? null,
                            'permissions' => $permissions,
                            'MitelToken' => $mitelToken,
                            'access_token' => $originalData->access_token,
                            'token_type' => 'bearer',
                            'expires_in' => config('jwt.ttl') * 60
                        ]
                    ]);
                } catch (\Exception $e) {
                    Log::error('Failed to retrieve MitelToken', [
                        'user_id' => $user->id,
                        'agent_username' => $user->agent->agent_username,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                    return response()->json([
                        'success' => false,
                        'message' => 'Failed to retrieve MitelToken',
                        'error' => $e->getMessage()
                    ], 500);
                }
            }
        }

        return $response;
    }

    /**
     * Username/Password authentication
     */
    private function usernamePasswordLogin(Request $request)
    {
        Log::info('Username/password login attempt', [
            'username' => $request->input('username'),
            'ip_address' => $request->ip()
        ]);

        $validator = Validator::make($request->all(), [
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            Log::warning('Login validation failed', [
                'username' => $request->input('username'),
                'errors' => $validator->errors()->toArray()
            ]);
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $credentials = $request->only('username', 'password');

        try {
            // Use AuthenticationService to handle both database and LDAP authentication
            $authService = new AuthenticationService();
            $authResult = $authService->authenticate($credentials);

            if (!$authResult['success']) {
                Log::warning('Authentication failed', [
                    'username' => $credentials['username'],
                    'reason' => $authResult['message'],
                    'ip_address' => $request->ip()
                ]);
                return response()->json([
                    'success' => false,
                    'message' => $authResult['message']
                ], 401);
            }

            $user = $authResult['user'];

            // Generate JWT token
            $token = JWTAuth::fromUser($user);

            // Record last login timestamp
            $user->last_login_at = now();
            $user->save();

            $user->load(['agent', 'role.permissions']);

            Log::info('Login successful', [
                'user_id' => $user->id,
                'username' => $user->username,
                'email' => $user->email,
                'role_id' => $user->role_id,
                'role_slug' => $user->role?->slug,
                'has_agent' => $user->agent !== null,
                'ip_address' => $request->ip()
            ]);

            // Get user permissions
            $permissions = $user->getPermissions();
            
            Log::info('User permissions retrieved', [
                'user_id' => $user->id,
                'role_id' => $user->role_id,
                'role_slug' => $user->role?->slug,
                'permissions_count' => count($permissions),
                'permissions' => $permissions
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'user' => $user,
                    'agent' => $user->agent ? $user->agent->toArray() : null,
                    'permissions' => $permissions,
                    'access_token' => $token,
                    'token_type' => 'bearer',
                    'expires_in' => config('jwt.ttl') * 60
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Login exception occurred', [
                'username' => $credentials['username'],
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Authentication failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Ignite Mitel token authentication
     */
    private function igniteTokenLogin(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ignite_token' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Verify token with Ignite Mitel
            $response = Http::withToken($request->ignite_token)
                ->get(env('IGNITE_MITEL_AUTH_ENDPOINT') . '/verify');

            if (!$response->successful()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid Ignite Mitel token'
                ], 401);
            }

            $userData = $response->json();

            // Find or create user
            $user = User::updateOrCreate(
                ['email' => $userData['email']],
                [
                    'name' => $userData['name'] ?? '',
                    'ignite_id' => $userData['id'] ?? null,
                ]
            );

            // Generate JWT token
            $token = JWTAuth::fromUser($user);

            // Record last login timestamp
            $user->last_login_at = now();
            $user->save();

            // Load role with permissions
            $user->load(['role.permissions']);

            // Get user permissions
            $permissions = $user->getPermissions();

            return response()->json([
                'success' => true,
                'data' => [
                    'user' => $user,
                    'permissions' => $permissions,
                    'access_token' => $token,
                    'MitelToken' => $request->ignite_token,
                    'token_type' => 'bearer',
                    'expires_in' => config('jwt.ttl') * 60
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get authenticated user information
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function me()
    {
        try {
                $user = auth()->user()->load(['agent', 'role.permissions']);
                
                // Get user permissions
                $permissions = $user->getPermissions();
                
                return response()->json([
                    'success' => true,
                    'data' => [
                        'user' => $user,
                        'agent' => $user->agent ? $user->agent->toArray() : null,
                        'permissions' => $permissions,
                    ]
                ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to get user information',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Logout user (invalidate token)
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout()
    {
        try {
            $user = auth()->user();

            // Hangup any active Mitel interactions assigned to this user's agent
            // Ticket stays In Progress so another agent can pick it up later
            if ($user?->agent) {
                $agentId = $user->agent->id;
                $activeTickets = Ticket::where('assigned_to', $agentId)
                    ->whereNotNull('mitel_interaction_id')
                    ->whereIn('status', ['Open', 'In Progress'])
                    ->get(['id', 'ticket_number', 'mitel_interaction_id']);

                if ($activeTickets->isNotEmpty()) {
                    try {
                        $mcx = app(McxOpenMediaService::class);
                        foreach ($activeTickets as $ticket) {
                            try {
                                $mcx->closeInteraction($ticket->mitel_interaction_id, 'agent_logout');
                                Log::info('[Logout] Mitel interaction hung up', [
                                    'ticket'   => $ticket->ticket_number,
                                    'mitel_id' => $ticket->mitel_interaction_id,
                                    'agent'    => $agentId,
                                ]);
                            } catch (\Throwable $e) {
                                Log::warning('[Logout] Failed to hangup interaction', [
                                    'ticket' => $ticket->ticket_number,
                                    'error'  => $e->getMessage(),
                                ]);
                            }
                            // Clear mitel link so sync-interactions doesn't auto-close the ticket
                            Ticket::withoutEvents(function () use ($ticket) {
                                $ticket->mitel_interaction_id = null;
                                $ticket->save();
                            });
                        }
                    } catch (\Throwable $e) {
                        Log::warning('[Logout] McxOpenMediaService init failed', ['error' => $e->getMessage()]);
                    }
                }
            }

            auth()->logout();

            return response()->json([
                'success' => true,
                'message' => 'Successfully logged out'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Logout failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Refresh JWT token
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function refresh()
    {
        try {
            $token = auth()->refresh();

            return response()->json([
                'success' => true,
                'data' => [
                    'access_token' => $token,
                    'token_type' => 'bearer',
                    'expires_in' => config('jwt.ttl') * 60
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Token refresh failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
