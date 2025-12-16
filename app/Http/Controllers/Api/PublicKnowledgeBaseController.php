<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use OpenSearch\ClientBuilder;

class PublicKnowledgeBaseController extends Controller
{
    private $elasticsearch;
    private $index = 'knowledge_base';

    public function __construct()
    {
        try {
            // Initialize OpenSearch client
            $hosts = [
                config('services.elasticsearch.host', 'localhost:9200')
            ];

            $clientBuilder = ClientBuilder::create()
                ->setHosts($hosts);

            // Add basic authentication if configured
            if (config('services.elasticsearch.username')) {
                $clientBuilder->setBasicAuthentication(
                    config('services.elasticsearch.username'),
                    config('services.elasticsearch.password')
                );
            }

            $this->elasticsearch = $clientBuilder->build();
        } catch (\Exception $e) {
            Log::error('OpenSearch connection failed: ' . $e->getMessage());
        }
    }

    /**
     * Search knowledge base articles
     * GET /api/v1/public/knowledge-base/search
     * 
     * Query params:
     * - q: search query (optional)
     * - category: filter by category (optional)
     * - limit: number of results (default 10, max 50)
     */
    public function search(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'q' => 'nullable|string|max:255',
                'category' => 'nullable|string',
                'limit' => 'nullable|integer|min:1|max:50'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $query = $request->input('q', '');
            $category = $request->input('category');
            $limit = $request->input('limit', 10);

            // Build Elasticsearch query
            $body = [
                'size' => $limit,
                'query' => [
                    'bool' => [
                        'must' => [],
                        'filter' => [
                            ['term' => ['status' => 'published']]
                        ]
                    ]
                ],
                'sort' => [
                    '_score' => ['order' => 'desc'],
                    'views' => ['order' => 'desc']
                ]
            ];

            // Add search query if provided
            if (!empty($query)) {
                $body['query']['bool']['must'][] = [
                    'multi_match' => [
                        'query' => $query,
                        'fields' => ['title^3', 'content', 'tags^2'],
                        'type' => 'best_fields',
                        'fuzziness' => 'AUTO'
                    ]
                ];
            } else {
                // If no query, match all published articles
                $body['query']['bool']['must'][] = [
                    'match_all' => (object)[]
                ];
            }

            // Add category filter if provided
            if ($category) {
                $body['query']['bool']['filter'][] = [
                    'term' => ['category.keyword' => $category]
                ];
            }

            $params = [
                'index' => $this->index,
                'body' => $body
            ];

            $response = $this->elasticsearch->search($params);

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
                    'views' => $source['views'] ?? 0,
                    'helpful_count' => $source['helpful_count'] ?? 0,
                    'not_helpful_count' => $source['not_helpful_count'] ?? 0,
                    'created_at' => $source['created_at'] ?? null,
                    'score' => $hit['_score']
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $articles,
                'total' => $response['hits']['total']['value'],
                'query' => $query
            ]);

        } catch (\Exception $e) {
            Log::error('KB search failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Search failed. Please try again.'
            ], 500);
        }
    }

    /**
     * Get single article by ID
     * GET /api/v1/public/knowledge-base/articles/{id}
     */
    public function getArticle(string $id)
    {
        try {
            // Get article from Elasticsearch
            $params = [
                'index' => $this->index,
                'id' => $id
            ];

            $response = $this->elasticsearch->get($params);
            $source = $response['_source'];

            // Increment view count
            $this->incrementViews($id);

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $id,
                    'title' => $source['title'],
                    'content' => $source['content'],
                    'category' => $source['category'],
                    'tags' => $source['tags'] ?? [],
                    'views' => ($source['views'] ?? 0) + 1, // Return incremented count
                    'helpful_count' => $source['helpful_count'] ?? 0,
                    'not_helpful_count' => $source['not_helpful_count'] ?? 0,
                    'author' => $source['author'] ?? null,
                    'created_at' => $source['created_at'] ?? null,
                    'updated_at' => $source['updated_at'] ?? null
                ]
            ]);

        } catch (\OpenSearch\Common\Exceptions\Missing404Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Article not found'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Get article failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve article'
            ], 500);
        }
    }

    /**
     * Get popular articles
     * GET /api/v1/public/knowledge-base/popular
     */
    public function getPopular(Request $request)
    {
        try {
            $limit = $request->input('limit', 10);

            $params = [
                'index' => $this->index,
                'body' => [
                    'size' => $limit,
                    'query' => [
                        'term' => ['status' => 'published']
                    ],
                    'sort' => [
                        'views' => ['order' => 'desc']
                    ]
                ]
            ];

            $response = $this->elasticsearch->search($params);

            $articles = [];
            foreach ($response['hits']['hits'] as $hit) {
                $source = $hit['_source'];
                $articles[] = [
                    'id' => $hit['_id'],
                    'title' => $source['title'],
                    'excerpt' => $this->generateExcerpt($source['content'], 150),
                    'category' => $source['category'],
                    'views' => $source['views'] ?? 0,
                    'helpful_count' => $source['helpful_count'] ?? 0
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $articles
            ]);

        } catch (\Exception $e) {
            Log::error('Get popular articles failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve popular articles'
            ], 500);
        }
    }

    /**
     * Get categories with article counts
     * GET /api/v1/public/knowledge-base/categories
     */
    public function getCategories()
    {
        try {
            $params = [
                'index' => $this->index,
                'body' => [
                    'size' => 0,
                    'query' => [
                        'term' => ['status' => 'published']
                    ],
                    'aggs' => [
                        'categories' => [
                            'terms' => [
                                'field' => 'category.keyword',
                                'size' => 50
                            ]
                        ]
                    ]
                ]
            ];

            $response = $this->elasticsearch->search($params);

            $categories = [];
            foreach ($response['aggregations']['categories']['buckets'] as $bucket) {
                $categories[] = [
                    'name' => $bucket['key'],
                    'count' => $bucket['doc_count']
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $categories
            ]);

        } catch (\Exception $e) {
            Log::error('Get categories failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve categories'
            ], 500);
        }
    }

    /**
     * Submit feedback on article
     * POST /api/v1/public/knowledge-base/articles/{id}/feedback
     * 
     * Body: { "helpful": true/false }
     */
    public function submitFeedback(string $id, Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'helpful' => 'required|boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $helpful = $request->input('helpful');
            $field = $helpful ? 'helpful_count' : 'not_helpful_count';

            // Update feedback count using script
            $params = [
                'index' => $this->index,
                'id' => $id,
                'body' => [
                    'script' => [
                        'source' => "ctx._source.$field = (ctx._source.$field ?: 0) + 1",
                        'lang' => 'painless'
                    ]
                ]
            ];

            $this->elasticsearch->update($params);

            return response()->json([
                'success' => true,
                'message' => 'Thank you for your feedback!'
            ]);

        } catch (\OpenSearch\Common\Exceptions\Missing404Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Article not found'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Submit feedback failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to submit feedback'
            ], 500);
        }
    }

    /**
     * Get FAQ articles
     * GET /api/v1/public/knowledge-base/faqs
     */
    public function getFAQs(Request $request)
    {
        try {
            $limit = $request->input('limit', 20);

            $params = [
                'index' => $this->index,
                'body' => [
                    'size' => $limit,
                    'query' => [
                        'bool' => [
                            'must' => [
                                ['term' => ['status' => 'published']],
                                ['term' => ['category.keyword' => 'FAQ']]
                            ]
                        ]
                    ],
                    'sort' => [
                        'views' => ['order' => 'desc']
                    ]
                ]
            ];

            $response = $this->elasticsearch->search($params);

            $faqs = [];
            foreach ($response['hits']['hits'] as $hit) {
                $source = $hit['_source'];
                $faqs[] = [
                    'id' => $hit['_id'],
                    'question' => $source['title'],
                    'answer' => $source['content'],
                    'views' => $source['views'] ?? 0
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $faqs
            ]);

        } catch (\Exception $e) {
            Log::error('Get FAQs failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve FAQs'
            ], 500);
        }
    }

    /**
     * Helper: Increment article view count
     */
    private function incrementViews(string $id)
    {
        try {
            $params = [
                'index' => $this->index,
                'id' => $id,
                'body' => [
                    'script' => [
                        'source' => 'ctx._source.views = (ctx._source.views ?: 0) + 1',
                        'lang' => 'painless'
                    ]
                ]
            ];

            $this->elasticsearch->update($params);
        } catch (\Exception $e) {
            Log::warning('Failed to increment views for article ' . $id . ': ' . $e->getMessage());
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
