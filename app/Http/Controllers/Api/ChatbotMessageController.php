<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MitelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ChatbotMessageController extends Controller
{
    public function __construct(private MitelService $mitel) {}

    /**
     * POST /api/v1/public/chatbot/message
     *
     * Receives a message from the citizen chat widget and forwards it
     * into the Mitel OpenMedia interaction so it appears in Ignite.
     *
     * Request body:
     *   interaction_id  string  Mitel interaction ID from /chatbot/escalate
     *   message         string  Customer's message text
     */
    public function send(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'interaction_id' => 'required|string',
            'message'        => 'required|string|max:4000',
            'agent_id'       => 'nullable|string',
        ]);

        try {
            // Resolve agentId — use the one sent by the widget, or look it up live
            $agentId = $validated['agent_id'] ?? null;
            if (!$agentId) {
                try {
                    $interaction = $this->mitel->getOpenMediaInteraction($validated['interaction_id']);
                    $agentId = $interaction['agentId'] ?? null;
                } catch (\Exception) {}
            }

            $this->mitel->sendOpenMediaMessage(
                $validated['interaction_id'],
                $validated['message'],
                $agentId
            );

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            Log::error('Failed to relay chatbot message to Mitel', [
                'interaction_id' => $validated['interaction_id'],
                'error'          => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Message could not be delivered.',
            ], 503);
        }
    }
}
