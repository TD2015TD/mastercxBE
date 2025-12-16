<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ElasticsearchService;

class CreateKnowledgeBaseArticlesIndex extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'opensearch:create-articles-index {--force : Force recreate the index}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create OpenSearch index for Knowledge Base Articles (with category mapping)';

    /**
     * Execute the console command.
     */
    public function handle(ElasticsearchService $esService)
    {
        $client = $esService->getClient();
        $index = 'knowledge_base_articles';

        try {
            // Check if index exists
            $exists = $client->indices()->exists(['index' => $index]);

            if ($exists) {
                if ($this->option('force')) {
                    $this->warn('Index already exists. Deleting...');
                    $client->indices()->delete(['index' => $index]);
                    $this->info('Index deleted.');
                } else {
                    $this->error('Index already exists. Use --force to recreate.');
                    return 1;
                }
            }

            // Create index with mappings
            $params = [
                'index' => $index,
                'body' => [
                    'settings' => [
                        'number_of_shards' => 1,
                        'number_of_replicas' => 1,
                        'analysis' => [
                            'analyzer' => [
                                'default' => [
                                    'type' => 'standard'
                                ]
                            ]
                        ]
                    ],
                    'mappings' => [
                        'properties' => [
                            'title' => [
                                'type' => 'text',
                                'fields' => [
                                    'keyword' => [
                                        'type' => 'keyword',
                                        'ignore_above' => 256
                                    ]
                                ]
                            ],
                            'content' => [
                                'type' => 'text'
                            ],
                            'category_code' => [
                                'type' => 'keyword'
                            ],
                            'author' => [
                                'type' => 'keyword'
                            ],
                            'published_at' => [
                                'type' => 'date'
                            ],
                            'created_at' => [
                                'type' => 'date'
                            ],
                            'updated_at' => [
                                'type' => 'date'
                            ]
                        ]
                    ]
                ]
            ];

            $response = $client->indices()->create($params);

            if ($response['acknowledged']) {
                $this->info('✓ Knowledge Base Articles index created successfully!');
                $this->line('Index name: ' . $index);
                return 0;
            } else {
                $this->error('Failed to create index.');
                return 1;
            }

        } catch (\Exception $e) {
            $this->error('Error: ' . $e->getMessage());
            return 1;
        }
    }
}
