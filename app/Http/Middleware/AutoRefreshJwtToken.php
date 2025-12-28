<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\JWTException;
use Symfony\Component\HttpFoundation\Response;

class AutoRefreshJwtToken
{
    /**
     * Handle an incoming request and automatically refresh JWT token if needed.
     *
     * This middleware checks the JWT token's expiration time and automatically
     * refreshes it if it's close to expiring, ensuring sessions remain active
     * for call center agents as long as they're actively using the system.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            // Attempt to authenticate the user with the current token
            $user = JWTAuth::parseToken()->authenticate();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found'
                ], 401);
            }

            // Get the current token payload
            $payload = JWTAuth::parseToken()->getPayload();

            // Get token expiration time
            $exp = $payload->get('exp');
            $now = time();

            // Calculate time until expiration
            $timeUntilExpiry = $exp - $now;

            // Auto-refresh threshold in seconds (default: 30 minutes)
            // If token expires in less than this time, refresh it
            $refreshThreshold = config('jwt.auto_refresh_threshold', 1800);

            // If token is close to expiring, refresh it automatically
            if ($timeUntilExpiry < $refreshThreshold) {
                try {
                    // Refresh the token
                    $newToken = JWTAuth::refresh(JWTAuth::getToken());

                    // Set the new token in the response header
                    $response = $next($request);
                    $response->headers->set('Authorization', 'Bearer ' . $newToken);
                    $response->headers->set('X-Token-Refreshed', 'true');

                    return $response;
                } catch (JWTException $e) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Could not refresh token'
                    ], 500);
                }
            }

            // Token is still valid and not close to expiring
            return $next($request);

        } catch (TokenExpiredException $e) {
            // Token has expired, try to refresh it
            try {
                $newToken = JWTAuth::refresh(JWTAuth::getToken());

                // Authenticate with the new token
                $user = JWTAuth::setToken($newToken)->authenticate();

                if (!$user) {
                    return response()->json([
                        'success' => false,
                        'message' => 'User not found'
                    ], 401);
                }

                // Continue with the request and include new token in response
                $response = $next($request);
                $response->headers->set('Authorization', 'Bearer ' . $newToken);
                $response->headers->set('X-Token-Refreshed', 'true');

                return $response;

            } catch (JWTException $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Token has expired and cannot be refreshed. Please login again.'
                ], 401);
            }
        } catch (JWTException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Token is invalid'
            ], 401);
        }
    }
}
