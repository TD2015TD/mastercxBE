<?php

namespace App\Http\Controllers\Api\Mitel;

use App\Http\Controllers\Controller;
use App\Services\MitelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class OpenMediaController extends Controller
{
    public function __construct(private MitelService $mitel) {}

    /**
     * GET /api/v1/mitel/openmedia
     *
     * List all active open media items. Supports OData query params
     * ($filter, $orderby, $top, $skip, $search) passed through as-is.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $params = $request->only(['$filter', '$orderby', '$top', '$skip', '$search', '$select']);
            $result = $this->mitel->getOpenMediaItems($params);
            return response()->json(['success' => true, 'data' => $result]);
        } catch (\Exception $e) {
            Log::error('OpenMedia index failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 502);
        }
    }

    /**
     * GET /api/v1/mitel/openmedia/queues
     *
     * List all active open media queues with links to active conversations.
     */
    public function queues(Request $request): JsonResponse
    {
        try {
            $params = $request->only(['$filter', '$orderby', '$top', '$skip', '$search', '$select']);
            $result = $this->mitel->getOpenMediaQueues($params);
            return response()->json(['success' => true, 'data' => $result]);
        } catch (\Exception $e) {
            Log::error('OpenMedia queues failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 502);
        }
    }

    /**
     * GET /api/v1/mitel/openmedia/{id}
     *
     * Get a single open media conversation.
     * Returns 404 if the interaction is in Failed or Handled state.
     */
    public function show(string $id): JsonResponse
    {
        try {
            $result = $this->mitel->getOpenMediaInteraction($id);
            return response()->json(['success' => true, 'data' => $result]);
        } catch (\Exception $e) {
            Log::error('OpenMedia show failed', ['id' => $id, 'error' => $e->getMessage()]);
            $status = str_contains($e->getMessage(), '404') ? 404 : 502;
            return response()->json(['success' => false, 'message' => $e->getMessage()], $status);
        }
    }

    /**
     * PUT /api/v1/mitel/openmedia/{id}
     *
     * Reroute a failed interaction or complete an interaction.
     *
     * To complete: send { "interactionState": "Handled" }
     * To reroute:  send { "queueName": "AnotherQueue" }
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $data = $request->all();

        try {
            $result = $this->mitel->updateOpenMediaInteraction($id, $data);
            return response()->json(['success' => true, 'data' => $result]);
        } catch (\Exception $e) {
            Log::error('OpenMedia update failed', ['id' => $id, 'error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 502);
        }
    }
}
