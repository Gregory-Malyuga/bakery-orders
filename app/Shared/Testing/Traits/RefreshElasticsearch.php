<?php

namespace Shared\Testing\Traits;

use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\Exception\ClientResponseException;

trait RefreshElasticsearch
{
    protected function setUpElasticsearchIndex(string $index): void
    {
        $client = app(Client::class);

        try {
            $client->indices()->delete(['index' => $index]);
        } catch (ClientResponseException) {
        }

        $mappingFile = database_path("elasticsearch/{$index}.json");
        /** @var array<string, mixed> $body */
        $body = file_exists($mappingFile)
            ? (array) json_decode((string) file_get_contents($mappingFile), true)
            : [];

        $client->indices()->create(['index' => $index, 'body' => $body]);
    }

    protected function tearDownElasticsearchIndex(string $index): void
    {
        try {
            app(Client::class)->indices()->delete(['index' => $index]);
        } catch (ClientResponseException) {
        }
    }
}
