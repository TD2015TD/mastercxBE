<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MitelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ChatbotEscalationController extends Controller
{
    public function __construct(private MitelService $mitel) {}

    /**
     * POST /api/v1/public/chatbot/escalate
     *
     * Called by the citizen portal when the customer presses "Chat dengan Pegawai".
     * Creates an OpenMedia interaction on Mitel and returns the interaction ID + queue position.
     *
     * Request body:
     *   name     string   Customer name
     *   ic       string   IC / passport number
     *   topic    string   FAQ topic selected
     *   faq_path string   Full FAQ breadcrumb trail
     *
     * Response:
     *   { interaction_id, position_in_queue }
     */
    public function escalate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'     => 'nullable|string|max:255',
            'ic'       => 'nullable|string|max:50',
            'topic'    => 'nullable|string|max:255',
            'faq_path' => 'nullable|string',
        ]);

        try {
            $queue = config('services.mitel.escalation_queue', 'ChatbotEscalation');

            $vars = [
                'Name'    => $validated['name']     ?? '',
                'IC'      => $validated['ic']        ?? '',
                'Topic'   => $validated['topic']     ?? '',
                'FaqPath' => $validated['faq_path']  ?? '',
                'Channel' => 'ChatbotPortal',
            ];

            $result = $this->mitel->escalateToAgent($queue, $vars);

            return response()->json([
                'success'          => true,
                'interaction_id'   => $result['Id'] ?? null,
                'position_in_queue' => $result['PositionInQueue'] ?? 1,
            ]);
        } catch (\Exception $e) {
            Log::error('Chatbot escalation failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to connect to the agent queue. Please try again.',
            ], 503);
        }
    }
}
