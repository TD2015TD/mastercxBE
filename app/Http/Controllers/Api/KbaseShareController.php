<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\KnowledgeBaseArticle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

class KbaseShareController extends Controller
{
    /**
     * POST /api/v1/kbase/share
     *
     * Agent drags a KBase document onto the chat drop zone.
     * Generates a 24-hour signed URL and logs the share event.
     * The signed URL is then sent to the customer via the ai-chat-service.
     *
     * Request body:
     *   article_id     string|int   KBase article ID
     *   interaction_id string       Mitel conversation / session ID
     */
    public function share(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'article_id'     => 'required',
            'interaction_id' => 'required|string',
        ]);

        try {
            $article = KnowledgeBaseArticle::findOrFail($validated['article_id']);

            // Generate a 24-hour signed download URL via the web route
            $signedUrl = URL::temporarySignedRoute(
                'kbase.download',
                now()->addHours(24),
                ['article' => $article->id]
            );

            // Log the share event
            DB::table('kbase_share_logs')->insert([
                'agent_id'       => Auth::id(),
                'interaction_id' => $validated['interaction_id'],
                'article_id'     => (string) $article->id,
                'article_title'  => $article->title,
                'signed_url'     => $signedUrl,
                'shared_at'      => now(),
            ]);

            Log::info('KBase document shared', [
                'agent_id'       => Auth::id(),
                'article_id'     => $article->id,
                'interaction_id' => $validated['interaction_id'],
            ]);

            return response()->json([
                'success'     => true,
                'signed_url'  => $signedUrl,
                'title'       => $article->title,
                'file_name'   => $article->file_name,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return response()->json(['success' => false, 'message' => 'Article not found'], 404);
        } catch (\Exception $e) {
            Log::error('KBase share failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to generate share link'], 500);
        }
    }

    /**
     * GET /kbase/download/{article}  (signed, web route)
     *
     * Serves the actual file download. Only accessible via valid signed URL.
     */
    public function download(Request $request, KnowledgeBaseArticle $article): Response
    {
        if (! $request->hasValidSignature()) {
            abort(403, 'Link has expired or is invalid.');
        }

        if (! $article->file_path || ! Storage::exists($article->file_path)) {
            abort(404, 'File not found.');
        }

        return Storage::download($article->file_path, $article->file_name ?? 'document');
    }
}
