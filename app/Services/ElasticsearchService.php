<?php

namespace App\Services;

use Elasticsearch\ClientBuilder;

class ElasticsearchService
{
    protected $client;

    public function __construct()
    {
        $this->client = ClientBuilder::create()
            ->setHosts([config('services.elasticsearch.host')])
            ->build();
    }

    public function getClient()
    {
        return $this->client;
    }

    // Example: Search
    public function search($params)
    {
        return $this->client->search($params);
    }

    // Example: Index a document
    public function index($params)
    {
        return $this->client->index($params);
    }
}
