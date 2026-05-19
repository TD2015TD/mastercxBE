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
     */
    public function share(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'article_id'     => 'required',
            'interaction_id' => 'required|string',
        ]);

        try {
            $article = KnowledgeBaseArticle::findOrFail($validated['article_id']);

            // Build URL from the actual request host — APP_URL may be localhost in .env
            $signedUrl = $request->getSchemeAndHttpHost() . '/api/v1/public/kbase/articles/' . $article->id . '/file';

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
                'success'    => true,
                'signed_url' => $signedUrl,
                'title'      => $article->title,
                'file_name'  => $article->file_name,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return response()->json(['success' => false, 'message' => 'Article not found'], 404);
        } catch (\Exception $e) {
            Log::error('KBase share failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to generate share link'], 500);
        }
    }

    /**
     * GET /kbase/view/{article}  (signed)
     *
     * Opens the article in the browser:
     *   - PDF / image → served inline so the browser renders it
     *   - Text content → rendered as a styled HTML page
     */
    public function view(Request $request, KnowledgeBaseArticle $article)
    {
        if (! $request->hasValidSignature()) {
            abort(403, 'Link has expired or is invalid.');
        }

        $hasFile = $article->file_path && Storage::disk('public')->exists($article->file_path);

        // Inline file (PDF, image, etc.)
        if ($hasFile) {
            $mime = Storage::disk('public')->mimeType($article->file_path);

            // For viewable types serve inline; everything else force-download
            $inlineMimes = ['application/pdf', 'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'];

            if (in_array($mime, $inlineMimes)) {
                $fileContents = Storage::disk('public')->get($article->file_path);
                return response($fileContents, 200)
                    ->header('Content-Type', $mime)
                    ->header('Content-Disposition', 'inline; filename="' . ($article->file_name ?? 'document') . '"');
            }

            // Non-viewable file type → download
            return Storage::disk('public')->download($article->file_path, $article->file_name ?? 'document');
        }

        // Text-only article → render as HTML
        $content  = e($article->content ?? '(Tiada kandungan)');
        $title    = e($article->title ?? 'Artikel');

        $html = <<<HTML
<!DOCTYPE html>
<html lang="ms">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{$title}</title>
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  body {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    background: #f8fafc;
    color: #1e293b;
    min-height: 100vh;
    padding: 32px 16px;
  }
  .card {
    max-width: 800px;
    margin: 0 auto;
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 1px 4px rgba(0,0,0,.08), 0 4px 16px rgba(0,0,0,.06);
    overflow: hidden;
  }
  .header {
    background: #1d4ed8;
    color: #fff;
    padding: 24px 32px;
  }
  .header h1 { font-size: 1.25rem; font-weight: 600; line-height: 1.4; }
  .header .meta { font-size: 0.8rem; opacity: .7; margin-top: 6px; }
  .body {
    padding: 32px;
    font-size: 0.95rem;
    line-height: 1.8;
    white-space: pre-wrap;
    word-break: break-word;
  }
  .footer {
    padding: 16px 32px;
    border-top: 1px solid #e2e8f0;
    font-size: 0.75rem;
    color: #94a3b8;
    text-align: center;
  }
</style>
</head>
<body>
  <div class="card">
    <div class="header">
      <h1>{$title}</h1>
      <div class="meta">Pangkalan Pengetahuan · Dokumen Rasmi</div>
    </div>
    <div class="body">{$content}</div>
    <div class="footer">Dikongsi oleh pegawai &mdash; pautan sah selama 24 jam</div>
  </div>
</body>
</html>
HTML;

        return response($html, 200)->header('Content-Type', 'text/html; charset=UTF-8');
    }

    /**
     * GET /kbase/download/{article}  (signed)
     *
     * Force-download the file. Kept for direct download links.
     */
    public function download(Request $request, KnowledgeBaseArticle $article): Response
    {
        if (! $request->hasValidSignature()) {
            abort(403, 'Link has expired or is invalid.');
        }

        if (! $article->file_path || ! Storage::disk('public')->exists($article->file_path)) {
            abort(404, 'File not found.');
        }

        return Storage::disk('public')->download($article->file_path, $article->file_name ?? 'document');
    }
}
