<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\KnowledgeBaseArticle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ChatbotController extends Controller
{
    /**
     * GET /api/v1/public/chatbot/categories
     * Returns FAQ categories from the lookups table (knowledge_base_category type),
     * filtered to only categories that have published articles.
     */
    public function categories(): JsonResponse
    {
        $activeCodes = KnowledgeBaseArticle::where('status', 'published')
            ->whereNotNull('published_at')
            ->whereNull('deleted_at')
            ->pluck('category_code')
            ->unique()
            ->values();

        $categories = DB::table('lookups')
            ->where('type', 'knowledge_base_category')
            ->where('is_active', true)
            ->whereNull('deleted_at')          // honour soft-deletes from CRM
            ->whereIn('code', $activeCodes)
            ->orderBy('sort_order')
            ->get(['code', 'name_en', 'name_ms', 'icon'])
            ->map(fn($row) => [
                'id'      => $row->code,
                'name_bm' => $row->name_ms,
                'name_en' => $row->name_en,
                'icon'    => $row->icon,
            ]);

        return response()->json($categories);
    }

    /**
     * GET /api/v1/public/chatbot/items/{categoryCode}
     * Returns published KB articles for a given category as FAQ items.
     */
    public function items(string $categoryCode): JsonResponse
    {
        $items = KnowledgeBaseArticle::where('status', 'published')
            ->whereNotNull('published_at')
            ->whereNull('deleted_at')
            ->where('category_code', $categoryCode)
            ->orderBy('created_at')
            ->get(['id', 'title'])
            ->map(fn($a) => [
                'id'          => $a->id,
                'question_bm' => $a->title,
                'question_en' => $a->title,
            ]);

        return response()->json($items);
    }

    /**
     * GET /api/v1/public/chatbot/answer/{articleId}
     * Returns the article content as the FAQ answer, with the file download link if available.
     */
    public function answer(int $articleId): JsonResponse
    {
        $article = KnowledgeBaseArticle::whereNotNull('published_at')
            ->whereNull('deleted_at')
            ->findOrFail($articleId);

        $documents = [];
        if ($article->file_path && $article->file_name) {
            $documents[] = [
                'id'    => $article->id,
                'title' => $article->file_name,
                'type'  => $article->file_type,
                'url'   => url('/api/v1/public/kbase/articles/' . $article->id . '/file'),
            ];
        }

        return response()->json([
            'answer'       => $article->content ?? 'Sila rujuk dokumen yang dilampirkan.',
            'answer_en'    => $article->content ?? 'Please refer to the attached document.',
            'documents'    => $documents,
            'can_escalate' => true,
        ]);
    }
}
