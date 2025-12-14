<?php

namespace App\Services;

use App\Models\KnowledgeBaseArticle;
use App\Models\Lookup;
use Illuminate\Support\Facades\Log;

class KnowledgeBaseArticleService
{
    protected $es;
    protected $index = 'knowledge_base_articles';

    public function __construct(ElasticsearchService $esService)
    {
        $this->es = $esService->getClient();
    }

    public function store($data)
    {
        // Store in DB
        $article = KnowledgeBaseArticle::create($data);
        // Index in Elasticsearch
        $params = [
            'index' => $this->index,
            'id'    => $article->id,
            'body'  => [
                'title' => $article->title,
                'content' => $article->content,
                'category_code' => $article->category_code,
                'author' => $article->author,
                'published_at' => $article->published_at,
            ]
        ];
        $this->es->index($params);
        return $article;
    }

    public function delete($id)
    {
        $article = KnowledgeBaseArticle::findOrFail($id);
        $article->delete();
        $this->es->delete([
            'index' => $this->index,
            'id' => $id
        ]);
        return true;
    }

    public function search($query, $category = null)
    {
        $params = [
            'index' => $this->index,
            'body' => [
                'query' => [
                    'bool' => [
                        'must' => [
                            [
                                'multi_match' => [
                                    'query' => $query,
                                    'fields' => ['title', 'content']
                                ]
                            ]
                        ],
                        'filter' => $category ? [
                            ['term' => ['category_code' => $category]]
                        ] : []
                    ]
                ]
            ]
        ];
        $results = $this->es->search($params);
        return $results['hits']['hits'] ?? [];
    }

    public function aggregateByCategory()
    {
        $params = [
            'index' => $this->index,
            'body' => [
                'size' => 0,
                'aggs' => [
                    'by_category' => [
                        'terms' => [
                            'field' => 'category_code.keyword',
                            'size' => 10
                        ]
                    ]
                ]
            ]
        ];
        $results = $this->es->search($params);
        $buckets = $results['aggregations']['by_category']['buckets'] ?? [];
        // Map category code to name
        $categories = Lookup::ofType('knowledge_base_category')->get()->keyBy('code');
        return collect($buckets)->map(function($bucket) use ($categories) {
            $code = $bucket['key'];
            return [
                'category_code' => $code,
                'category_name' => $categories[$code]->getLocalizedNameAttribute() ?? $code,
                'count' => $bucket['doc_count']
            ];
        });
    }
}
