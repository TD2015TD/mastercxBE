<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Elastic\Elasticsearch\ClientBuilder;

class KnowledgeBaseController extends Controller
{
    private $opensearch;
    private $index = 'knowledge_base';

    public function __construct()
    {
        try {
            // Initialize OpenSearch client
            $hosts = [
                env('OPENSEARCH_HOST', 'localhost:9200')
            ];

            $this->opensearch = ClientBuilder::create()
                ->setHosts($hosts)
                ->build();
        } catch (\Exception $e) {
            Log::error('OpenSearch connection failed: ' . $e->getMessage());
        }
    }

    /**
     * List all articles (with filters)
     * GET /api/v1/knowledge-base
     */
    public function index(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'status' => 'nullable|in:draft,published,archived',
                'category' => 'nullable|string',
                'author_id' => 'nullable|integer',
                'page' => 'nullable|integer|min:1',
                'per_page' => 'nullable|integer|min:1|max:100'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $page = $request->input('page', 1);
            $perPage = $request->input('per_page', 20);
            $from = ($page - 1) * $perPage;

            // Build query
            $body = [
                'from' => $from,
                'size' => $perPage,
                'query' => [
                    'bool' => [
                        'must' => []
                    ]
                ],
                'sort' => [
                    'updated_at' => ['order' => 'desc']
                ]
            ];

            // Add filters
            if ($request->has('status')) {
                $body['query']['bool']['must'][] = [
                    'term' => ['status' => $request->input('status')]
                ];
            }

            if ($request->has('category')) {
                $body['query']['bool']['must'][] = [
                    'term' => ['category.keyword' => $request->input('category')]
                ];
            }

            if ($request->has('author_id')) {
                $body['query']['bool']['must'][] = [
                    'term' => ['author_id' => $request->input('author_id')]
                ];
            }

            // If no filters, match all
            if (empty($body['query']['bool']['must'])) {
                $body['query'] = ['match_all' => (object)[]];
            }

            $params = [
                'index' => $this->index,
                'body' => $body
            ];

            $response = $this->opensearch->search($params);

            // Format results
            $articles = [];
            foreach ($response['hits']['hits'] as $hit) {
                $source = $hit['_source'];
                $articles[] = [
                    'id' => $hit['_id'],
                    'title' => $source['title'],
                    'excerpt' => $this->generateExcerpt($source['content'], 200),
                    'category' => $source['category'],
                    'tags' => $source['tags'] ?? [],
                    'status' => $source['status'],
                    'views' => $source['views'] ?? 0,
                    'helpful_count' => $source['helpful_count'] ?? 0,
                    'not_helpful_count' => $source['not_helpful_count'] ?? 0,
                    'author_id' => $source['author_id'] ?? null,
                    'author' => $source['author'] ?? null,
                    'created_at' => $source['created_at'] ?? null,
                    'updated_at' => $source['updated_at'] ?? null
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $articles,
                'pagination' => [
                    'total' => $response['hits']['total']['value'],
                    'per_page' => $perPage,
                    'current_page' => $page,
                    'last_page' => ceil($response['hits']['total']['value'] / $perPage)
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('List articles failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve articles'
            ], 500);
        }
    }

    /**
     * Create new article
     * POST /api/v1/knowledge-base
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'title' => 'required|string|max:500',
                'content' => 'required|string',
                'category' => 'required|string',
                'tags' => 'nullable|array',
                'tags.*' => 'string',
                'status' => 'nullable|in:draft,published,archived'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = Auth::user();
            $now = now()->toIso8601String();

            $article = [
                'title' => $request->input('title'),
                'content' => $request->input('content'),
                'category' => $request->input('category'),
                'tags' => $request->input('tags', []),
                'status' => $request->input('status', 'draft'),
                'views' => 0,
                'helpful_count' => 0,
                'not_helpful_count' => 0,
                'author_id' => $user->id,
                'author' => $user->name,
                'created_at' => $now,
                'updated_at' => $now
            ];

            // Index document in OpenSearch
            $params = [
                'index' => $this->index,
                'body' => $article
            ];

            $response = $this->opensearch->index($params);

            return response()->json([
                'success' => true,
                'message' => 'Article created successfully',
                'data' => [
                    'id' => $response['_id'],
                    ...$article
                ]
            ], 201);

        } catch (\Exception $e) {
            Log::error('Create article failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create article'
            ], 500);
        }
    }

    /**
     * Get single article
     * GET /api/v1/knowledge-base/{id}
     */
    public function show(string $id)
    {
        try {
            $params = [
                'index' => $this->index,
                'id' => $id
            ];

            $response = $this->opensearch->get($params);
            $source = $response['_source'];

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $id,
                    ...$source
                ]
            ]);

        } catch (\Elastic\Elasticsearch\Exception\ClientResponseException $e) {
            if ($e->getCode() === 404) {
                return response()->json([
                    'success' => false,
                    'message' => 'Article not found'
                ], 404);
            }
            throw $e;
        } catch (\Exception $e) {
            Log::error('Get article failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve article'
            ], 500);
        }
    }

    /**
     * Update article
     * PUT /api/v1/knowledge-base/{id}
     */
    public function update(Request $request, string $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'title' => 'nullable|string|max:500',
                'content' => 'nullable|string',
                'category' => 'nullable|string',
                'tags' => 'nullable|array',
                'tags.*' => 'string',
                'status' => 'nullable|in:draft,published,archived'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            // Build update document
            $updateData = [];
            if ($request->has('title')) {
                $updateData['title'] = $request->input('title');
            }
            if ($request->has('content')) {
                $updateData['content'] = $request->input('content');
            }
            if ($request->has('category')) {
                $updateData['category'] = $request->input('category');
            }
            if ($request->has('tags')) {
                $updateData['tags'] = $request->input('tags');
            }
            if ($request->has('status')) {
                $updateData['status'] = $request->input('status');
            }

            $updateData['updated_at'] = now()->toIso8601String();

            // Update document
            $params = [
                'index' => $this->index,
                'id' => $id,
                'body' => [
                    'doc' => $updateData
                ]
            ];

            $this->opensearch->update($params);

            return response()->json([
                'success' => true,
                'message' => 'Article updated successfully'
            ]);

        } catch (\Elastic\Elasticsearch\Exception\ClientResponseException $e) {
            if ($e->getCode() === 404) {
                return response()->json([
                    'success' => false,
                    'message' => 'Article not found'
                ], 404);
            }
            throw $e;
        } catch (\Exception $e) {
            Log::error('Update article failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update article'
            ], 500);
        }
    }

    /**
     * Delete article
     * DELETE /api/v1/knowledge-base/{id}
     */
    public function destroy(string $id)
    {
        try {
            $params = [
                'index' => $this->index,
                'id' => $id
            ];

            $this->opensearch->delete($params);

            return response()->json([
                'success' => true,
                'message' => 'Article deleted successfully'
            ]);

        } catch (\Elastic\Elasticsearch\Exception\ClientResponseException $e) {
            if ($e->getCode() === 404) {
                return response()->json([
                    'success' => false,
                    'message' => 'Article not found'
                ], 404);
            }
            throw $e;
        } catch (\Exception $e) {
            Log::error('Delete article failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete article'
            ], 500);
        }
    }

    /**
     * Bulk import articles
     * POST /api/v1/knowledge-base/bulk-import
     */
    public function bulkImport(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'articles' => 'required|array',
                'articles.*.title' => 'required|string|max:500',
                'articles.*.content' => 'required|string',
                'articles.*.category' => 'required|string',
                'articles.*.tags' => 'nullable|array',
                'articles.*.status' => 'nullable|in:draft,published,archived'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = Auth::user();
            $now = now()->toIso8601String();
            $body = [];

            foreach ($request->input('articles') as $articleData) {
                // Index action
                $body[] = [
                    'index' => [
                        '_index' => $this->index
                    ]
                ];

                // Document
                $body[] = [
                    'title' => $articleData['title'],
                    'content' => $articleData['content'],
                    'category' => $articleData['category'],
                    'tags' => $articleData['tags'] ?? [],
                    'status' => $articleData['status'] ?? 'draft',
                    'views' => 0,
                    'helpful_count' => 0,
                    'not_helpful_count' => 0,
                    'author_id' => $user->id,
                    'author' => $user->name,
                    'created_at' => $now,
                    'updated_at' => $now
                ];
            }

            $params = ['body' => $body];
            $response = $this->opensearch->bulk($params);

            // Check for errors
            $errors = [];
            if ($response['errors']) {
                foreach ($response['items'] as $item) {
                    if (isset($item['index']['error'])) {
                        $errors[] = $item['index']['error'];
                    }
                }
            }

            return response()->json([
                'success' => !$response['errors'],
                'message' => $response['errors'] ? 'Some articles failed to import' : 'All articles imported successfully',
                'total' => count($request->input('articles')),
                'errors' => $errors
            ], $response['errors'] ? 207 : 201);

        } catch (\Exception $e) {
            Log::error('Bulk import failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to import articles'
            ], 500);
        }
    }

    /**
     * Get statistics
     * GET /api/v1/knowledge-base/statistics
     */
    public function statistics()
    {
        try {
            $params = [
                'index' => $this->index,
                'body' => [
                    'size' => 0,
                    'aggs' => [
                        'total_articles' => [
                            'value_count' => ['field' => '_id']
                        ],
                        'by_status' => [
                            'terms' => ['field' => 'status']
                        ],
                        'by_category' => [
                            'terms' => ['field' => 'category.keyword', 'size' => 50]
                        ],
                        'total_views' => [
                            'sum' => ['field' => 'views']
                        ],
                        'avg_helpful_rating' => [
                            'avg' => ['field' => 'helpful_count']
                        ]
                    ]
                ]
            ];

            $response = $this->opensearch->search($params);
            $aggs = $response['aggregations'];

            return response()->json([
                'success' => true,
                'data' => [
                    'total_articles' => $aggs['total_articles']['value'],
                    'total_views' => $aggs['total_views']['value'],
                    'avg_helpful_rating' => round($aggs['avg_helpful_rating']['value'] ?? 0, 2),
                    'by_status' => $aggs['by_status']['buckets'],
                    'by_category' => $aggs['by_category']['buckets']
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Get statistics failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve statistics'
            ], 500);
        }
    }

    /**
     * Helper: Generate excerpt from content
     */
    private function generateExcerpt(string $content, int $length = 200): string
    {
        $content = strip_tags($content);
        if (strlen($content) <= $length) {
            return $content;
        }
        return substr($content, 0, $length) . '...';
    }
}
