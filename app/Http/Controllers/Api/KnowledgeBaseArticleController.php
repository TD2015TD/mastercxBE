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
            $query = KnowledgeBaseArticle::with('category');

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

            $data = [
                'title' => $request->title,
                'category_code' => $request->category_code,
                'author' => Auth::user()->name ?? 'System',
                'published_at' => now(),
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
                'title' => 'nullable|string|max:500',
                'category_code' => 'nullable|string|exists:lookups,code',
                'file' => 'nullable|file|mimes:pdf,doc,docx,txt|max:10240',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            // Update basic fields
            if ($request->has('title')) {
                $article->title = $request->title;
            }

            if ($request->has('category_code')) {
                $article->category_code = $request->category_code;
            }

            // Handle file upload for update
            if ($request->hasFile('file')) {
                $file = $request->file('file');
                $content = $this->articleService->parseDocument($file);

                // Store new file
                $filePath = $file->store('knowledge-base-documents', 'public');

                $article->content = $content;
                $article->file_path = $filePath;
                $article->file_name = $file->getClientOriginalName();
                $article->file_type = $file->getClientOriginalExtension();
            }

            $article->save();

            // Update in OpenSearch
            $this->articleService->store($article->toArray());

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
        try {
            $query = $request->input('q', '');
            $category = $request->input('category_code');

            $results = $this->articleService->search($query, $category);

            return response()->json([
                'success' => true,
                'data' => $results
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Search failed'
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
}
