<?php

namespace App\Http\Controllers\Api\Mitel;

use App\Http\Controllers\Controller;
use App\Services\MitelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache; // Not strictly needed here, but kept for context

class MitelTokenController extends Controller
{
    protected MitelService $mitelService;

    public function __construct(MitelService $mitelService)
    {
        $this->mitelService = $mitelService;
    }

    /**
     * Get Mitel access token for SignalR connection
     * * This returns the same OAuth token used for API calls,
     * which can be used to authenticate SignalR connections
     */
    public function getToken(): JsonResponse
    {
        try {
            // **Simplified: Call the public authenticate method directly**
            $token = $this->mitelService->authenticate();

            return response()->json([
                'success' => true,
                'token' => $token,
                'base_url' => config('services.mitel.base_url'),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get Mitel token',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}