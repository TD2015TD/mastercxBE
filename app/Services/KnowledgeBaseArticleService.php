<?php

namespace App\Services;

use App\Models\KnowledgeBaseArticle;
use App\Models\Lookup;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Smalot\PdfParser\Parser as PdfParser;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Settings;

class KnowledgeBaseArticleService
{
    protected $es;
    protected $index = 'knowledge_base_articles';

    public function __construct(ElasticsearchService $esService)
    {
        $this->es = $esService->getClient();
    }

    /**
     * Parse uploaded document and extract text content
     *
     * @param \Illuminate\Http\UploadedFile $file
     * @return string
     * @throws \Exception
     */
    public function parseDocument($file)
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $content = '';

        try {
            switch ($extension) {
                case 'pdf':
                    $content = $this->parsePdf($file);
                    break;

                case 'doc':
                case 'docx':
                    $content = $this->parseWord($file);
                    break;

                case 'txt':
                    $content = file_get_contents($file->getRealPath());
                    break;

                default:
                    throw new \Exception("Unsupported file format: {$extension}. Supported formats: PDF, DOC, DOCX, TXT");
            }

            return trim($content);
        } catch (\Exception $e) {
            Log::error("Document parsing failed: " . $e->getMessage());
            throw new \Exception("Failed to parse document: " . $e->getMessage());
        }
    }

    /**
     * Parse PDF document
     *
     * @param \Illuminate\Http\UploadedFile $file
     * @return string
     */
    private function parsePdf($file)
    {
        $parser = new PdfParser();
        $pdf = $parser->parseFile($file->getRealPath());
        $text = $pdf->getText();

        // Clean up extra whitespace and normalize line breaks
        $text = preg_replace('/\s+/', ' ', $text);
        $text = str_replace(['\r\n', '\r'], '\n', $text);

        return $text;
    }

    /**
     * Parse Word document (DOC/DOCX)
     *
     * @param \Illuminate\Http\UploadedFile $file
     * @return string
     */
    private function parseWord($file)
    {
        $phpWord = IOFactory::load($file->getRealPath());
        $content = '';

        foreach ($phpWord->getSections() as $section) {
            foreach ($section->getElements() as $element) {
                if (method_exists($element, 'getText')) {
                    $content .= $element->getText() . "\n";
                } elseif (method_exists($element, 'getElements')) {
                    // Handle nested elements (like tables)
                    foreach ($element->getElements() as $childElement) {
                        if (method_exists($childElement, 'getText')) {
                            $content .= $childElement->getText() . "\n";
                        }
                    }
                }
            }
        }

        return $content;
    }

    /**
     * Store article with optional file upload
     *
     * @param array $data
     * @param \Illuminate\Http\UploadedFile|null $file
     * @return KnowledgeBaseArticle
     */
    public function storeWithFile($data, $file = null)
    {
        // Parse document if file is provided
        if ($file) {
            $data['content'] = $this->parseDocument($file);

            // Store original file
            $filePath = $file->store('knowledge-base-documents', 'public');
            $data['file_path'] = $filePath;
            $data['file_name'] = $file->getClientOriginalName();
            $data['file_type'] = $file->getClientOriginalExtension();
        }

        return $this->store($data);
    }

    public function store($data)
    {
        // Store in DB
        $article = KnowledgeBaseArticle::create($data);
        // Index in Elasticsearch (non-fatal: article is saved even if ES is unavailable)
        try {
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
        } catch (\Exception $e) {
            Log::warning('KB article ES indexing failed (non-fatal): ' . $e->getMessage());
        }
        return $article;
    }

    public function delete($id)
    {
        $article = KnowledgeBaseArticle::findOrFail($id);
        $article->delete();
        // Remove from Elasticsearch (non-fatal: DB record is already deleted)
        try {
            $this->es->delete([
                'index' => $this->index,
                'id' => $id
            ]);
        } catch (\Exception $e) {
            Log::warning('KB article ES delete failed (non-fatal): ' . $e->getMessage());
        }
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
