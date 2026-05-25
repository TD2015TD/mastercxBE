<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\KnowledgeBaseArticle;
use App\Models\Lookup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class PublicKnowledgeBaseController extends Controller
{
    /**
     * Search knowledge base articles
     * GET /api/v1/public/knowledge-base/search
     * Query params: q, category, limit
     */
    public function search(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'q'        => 'nullable|string|max:255',
                'category' => 'nullable|string',
                'limit'    => 'nullable|integer|min:1|max:50',
            ]);

            if ($validator->fails()) {
                return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
            }

            $query    = $request->input('q', '');
            $category = $request->input('category');
            $limit    = (int) $request->input('limit', 10);

            $qb = KnowledgeBaseArticle::with('category')
                ->where('status', 'published')
                ->orderByDesc('created_at');

            if (!empty($query)) {
                $qb->where(function ($q) use ($query) {
                    $q->where('title', 'ilike', "%{$query}%")
                      ->orWhere('content', 'ilike', "%{$query}%");
                });
            }

            if ($category) {
                $qb->where('category_code', $category);
            }

            $articles = $qb->limit($limit)->get()->map(fn($a) => $this->formatArticle($a));

            return response()->json([
                'success' => true,
                'data'    => $articles,
                'total'   => $articles->count(),
                'query'   => $query,
            ]);

        } catch (\Exception $e) {
            Log::error('KB search failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Search failed. Please try again.'], 500);
        }
    }

    /**
     * Get single article by ID
     * GET /api/v1/public/knowledge-base/articles/{id}
     */
    public function getArticle(string $id)
    {
        try {
            $article = KnowledgeBaseArticle::with('category')
                ->where('status', 'published')
                ->findOrFail((int) $id);

            return response()->json([
                'success' => true,
                'data'    => $this->formatArticle($article, true),
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Article not found'], 404);
        } catch (\Exception $e) {
            Log::error('Get article failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to retrieve article'], 500);
        }
    }

    /**
     * Get popular / recent articles
     * GET /api/v1/public/knowledge-base/popular
     */
    public function getPopular(Request $request)
    {
        try {
            $limit = (int) $request->input('limit', 10);

            $articles = KnowledgeBaseArticle::with('category')
                ->where('status', 'published')
                ->orderByDesc('created_at')
                ->limit($limit)
                ->get()
                ->map(fn($a) => $this->formatArticle($a));

            return response()->json(['success' => true, 'data' => $articles]);

        } catch (\Exception $e) {
            Log::error('Get popular articles failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to retrieve popular articles'], 500);
        }
    }

    /**
     * Get categories with article counts
     * GET /api/v1/public/knowledge-base/categories
     */
    public function getCategories()
    {
        try {
            $categories = Lookup::where('type', 'knowledge_base_category')
                ->whereNull('deleted_at')
                ->get()
                ->map(function ($lookup) {
                    $count = KnowledgeBaseArticle::where('category_code', $lookup->code)
                        ->where('status', 'published')
                        ->count();

                    return [
                        'code'     => $lookup->code,
                        'name'     => $lookup->name_en ?: $lookup->name_ms ?: $lookup->code,
                        'name_ms'  => $lookup->name_ms,
                        'name_en'  => $lookup->name_en,
                        'count'    => $count,
                    ];
                })
                ->filter(fn($c) => $c['count'] > 0)
                ->values();

            return response()->json(['success' => true, 'data' => $categories]);

        } catch (\Exception $e) {
            Log::error('Get categories failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to retrieve categories'], 500);
        }
    }

    /**
     * Submit feedback on article
     * POST /api/v1/public/knowledge-base/articles/{id}/feedback
     */
    public function submitFeedback(string $id, Request $request)
    {
        // Feedback tracking not yet implemented on DB model — return success silently
        return response()->json(['success' => true, 'message' => 'Thank you for your feedback!']);
    }

    /**
     * Get FAQ articles
     * GET /api/v1/public/knowledge-base/faqs
     */
    public function getFAQs(Request $request)
    {
        try {
            $limit = (int) $request->input('limit', 20);

            $articles = KnowledgeBaseArticle::with('category')
                ->where('status', 'published')
                ->where('category_code', 'faqs')
                ->orderByDesc('created_at')
                ->limit($limit)
                ->get()
                ->map(fn($a) => [
                    'id'       => $a->id,
                    'question' => $a->title,
                    'answer'   => $a->content,
                ]);

            return response()->json(['success' => true, 'data' => $articles]);

        } catch (\Exception $e) {
            Log::error('Get FAQs failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to retrieve FAQs'], 500);
        }
    }

    // -----------------------------------------------------------------------

    private function formatArticle(KnowledgeBaseArticle $article, bool $fullContent = false): array
    {
        $categoryName = $article->category
            ? ($article->category->name_en ?: $article->category->name_ms ?: $article->category_code)
            : $article->category_code;

        $fileUrl = null;
        if ($article->file_path) {
            $fileUrl = request()->getSchemeAndHttpHost() . '/api/v1/public/kbase/articles/' . $article->id . '/file';
        }

        return [
            'id'           => $article->id,
            'title'        => $article->title,
            'content'      => $fullContent ? $article->content : null,
            'excerpt'      => $this->generateExcerpt($article->content ?? '', 200),
            'category'     => $categoryName,
            'category_code'=> $article->category_code,
            'author'       => $article->author,
            'file_url'     => $fileUrl,
            'file_name'    => $article->file_name,
            'file_type'    => $article->file_type,
            'views'        => 0,
            'helpful_count'=> 0,
            'created_at'   => $article->created_at?->toIso8601String(),
            'updated_at'   => $article->updated_at?->toIso8601String(),
        ];
    }

    private function generateExcerpt(string $content, int $length = 200): string
    {
        $content = strip_tags($content);
        return strlen($content) <= $length ? $content : substr($content, 0, $length) . '...';
    }
}
