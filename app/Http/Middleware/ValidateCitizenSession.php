<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\CitizenAuthSession;

class ValidateCitizenSession
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->header('X-Session-Token');

        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'Session token required',
            ], 401);
        }

        $sessionData = CitizenAuthSession::verifySessionToken($token);

        if (!$sessionData) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired session',
            ], 401);
        }

        // Attach session data to request
        $request->merge([
            'citizen_phone_number' => $sessionData['phone_number'],
            'citizen_email' => $sessionData['email'],
        ]);

        return $next($request);
    }
}
