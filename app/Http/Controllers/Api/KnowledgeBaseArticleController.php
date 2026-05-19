<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\KnowledgeBaseArticleService;
use App\Models\KnowledgeBaseArticle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class KnowledgeBaseArticleController extends Controller
{
    protected $articleService;

    public function __construct(KnowledgeBaseArticleService $articleService)
    {
        $this->articleService = $articleService;
    }

    /**
     * List all articles
     * GET /api/v1/knowledge-base-articles
     */
    public function index(Request $request)
    {
        try {
            $user     = Auth::user();
            $roleSlug = $user?->role?->slug ?? 'agent';
            $isAgent  = $roleSlug === 'agent';

            $query = KnowledgeBaseArticle::with('category');

            // Agents:
            //   ?status=draft  → only their own drafts (for "My Submissions")
            //   no status      → published + their own drafts (browse view)
            // Supervisors/admins: everything, optionally filtered by ?status=
            if ($isAgent) {
                if ($request->input('status') === 'draft') {
                    $query->where('status', 'draft')
                          ->where('uploaded_by', $user->id);
                } else {
                    $query->where(function ($q) use ($user) {
                        $q->where('status', 'published')
                          ->orWhere(function ($q2) use ($user) {
                              $q2->where('status', 'draft')
                                 ->where('uploaded_by', $user->id);
                          });
                    });
                }
            } elseif ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // Filter by category
            if ($request->has('category_code')) {
                $query->where('category_code', $request->category_code);
            }

            // Search
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                      ->orWhere('content', 'like', "%{$search}%");
                });
            }

            $articles = $query->orderBy('updated_at', 'desc')->paginate(20);

            return response()->json([
                'success' => true,
                'data' => $articles
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve articles'
            ], 500);
        }
    }

    /**
     * Store article with file upload
     * POST /api/v1/knowledge-base-articles
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'title' => 'required|string|max:500',
                'category_code' => 'required|string|exists:lookups,code',
                'file' => 'nullable|file|mimes:pdf,doc,docx,txt|max:10240', // 10MB max
                'content' => 'required_without:file|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = Auth::user();
            $roleSlug = $user?->role?->slug ?? 'agent';
            $isAgent = $roleSlug === 'agent';

            // Agents can only create drafts
            $status = $isAgent ? 'draft' : ($request->input('status', 'draft'));

            $data = [
                'title'         => $request->title,
                'category_code' => $request->category_code,
                'author'        => $user->name ?? 'System',
                'uploaded_by'   => $user->id ?? null,
                'status'        => $status,
                'published_at'  => $status === 'published' ? now() : null,
            ];

            // If no file, use manual content
            if (!$request->hasFile('file')) {
                $data['content'] = $request->input('content');
            }

            $article = $this->articleService->storeWithFile(
                $data,
                $request->hasFile('file') ? $request->file('file') : null
            );

            return response()->json([
                'success' => true,
                'message' => 'Article created successfully',
                'data' => $article->load('category')
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create article: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get single article
     * GET /api/v1/knowledge-base-articles/{id}
     */
    public function show($id)
    {
        try {
            $article = KnowledgeBaseArticle::with('category')->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $article
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Article not found'
            ], 404);
        }
    }

    /**
     * Update article
     * PUT/PATCH /api/v1/knowledge-base-articles/{id}
     */
    public function update(Request $request, $id)
    {
        try {
            $article = KnowledgeBaseArticle::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'title'         => 'nullable|string|max:500',
                'category_code' => 'nullable|string|exists:lookups,code',
                'status'        => 'nullable|in:draft,published,archived',
                'file'          => 'nullable|file|mimes:pdf,doc,docx,txt|max:10240',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            if ($request->has('title'))         $article->title         = $request->title;
            if ($request->has('category_code')) $article->category_code = $request->category_code;
            if ($request->has('status'))        $article->status        = $request->status;

            // Handle file upload for update — store file only, no content extraction
            if ($request->hasFile('file')) {
                $file = $request->file('file');
                $filePath = $file->store('knowledge-base-documents', 'public');

                $article->file_path = $filePath;
                $article->file_name = $file->getClientOriginalName();
                $article->file_type = $file->getClientOriginalExtension();
            }

            $article->save();

            // Update ES index only — do NOT call service->store() which creates a new DB record
            $this->articleService->updateIndex($article);

            return response()->json([
                'success' => true,
                'message' => 'Article updated successfully',
                'data' => $article->load('category')
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update article: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete article
     * DELETE /api/v1/knowledge-base-articles/{id}
     */
    public function destroy($id)
    {
        try {
            $this->articleService->delete($id);

            return response()->json([
                'success' => true,
                'message' => 'Article deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete article'
            ], 500);
        }
    }

    /**
     * Search articles using OpenSearch
     * GET /api/v1/knowledge-base-articles/search
     */
    public function search(Request $request)
    {
        $q        = $request->input('q', '');
        $category = $request->input('category_code');

        // Try Elasticsearch first
        try {
            $results = $this->articleService->search($q, $category);

            return response()->json([
                'success' => true,
                'data'    => $results,
                'source'  => 'elasticsearch',
            ]);
        } catch (\Exception $esException) {
            // ES unavailable — fall back to database LIKE search
        }

        try {
            $dbQuery = KnowledgeBaseArticle::where('status', 'live');

            if ($q !== '') {
                $dbQuery->where(function ($builder) use ($q) {
                    $builder->where('title', 'ilike', "%{$q}%")
                            ->orWhere('content', 'ilike', "%{$q}%");
                });
            }

            if ($category) {
                $dbQuery->where('category_code', $category);
            }

            $articles = $dbQuery->get();

            // Transform to the same _id/_source shape the frontend expects
            $results = $articles->map(fn($a) => [
                '_id'     => (string) $a->id,
                '_source' => [
                    'title'         => $a->title,
                    'content'       => $a->content,
                    'category_code' => $a->category_code,
                    'author'        => $a->author,
                    'published_at'  => $a->published_at,
                ],
            ])->values()->all();

            return response()->json([
                'success' => true,
                'data'    => $results,
                'source'  => 'database',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Search failed',
            ], 500);
        }
    }

    /**
     * Get statistics by category
     * GET /api/v1/knowledge-base-articles/stats
     */
    public function stats()
    {
        try {
            $stats = $this->articleService->aggregateByCategory();

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve statistics'
            ], 500);
        }
    }

    /**
     * Publish a draft article (supervisor/admin only)
     * POST /api/v1/knowledge-base-articles/{id}/publish
     */
    public function publish($id)
    {
        $roleSlug = Auth::user()?->role?->slug ?? 'agent';
        if ($roleSlug === 'agent') {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        try {
            $article = KnowledgeBaseArticle::findOrFail($id);
            $article->status       = 'published';
            $article->published_at = $article->published_at ?? now();
            $article->save();

            return response()->json([
                'success' => true,
                'message' => 'Article published successfully',
                'data'    => $article->load('category'),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to publish article: ' . $e->getMessage()
            ], 500);
        }
    }
}
