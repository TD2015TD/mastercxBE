<?php

namespace App\Services;

use OpenSearch\ClientBuilder;

class ElasticsearchService
{
    protected $client;

    public function __construct()
    {
        // Configure OpenSearch client
        $hosts = [config('services.elasticsearch.host')];

        $clientBuilder = ClientBuilder::create()
            ->setHosts($hosts);

        // Add basic authentication if configured
        if (config('services.elasticsearch.username')) {
            $clientBuilder->setBasicAuthentication(
                config('services.elasticsearch.username'),
                config('services.elasticsearch.password')
            );
        }

        // Build client
        $this->client = $clientBuilder->build();
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
