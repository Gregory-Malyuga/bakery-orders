<?php

namespace Shared\Elasticsearch\Commands;

use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\Exception\ClientResponseException;
use Elastic\Elasticsearch\Response\Elasticsearch as EsResponse;
use Illuminate\Console\Command;
use Illuminate\Contracts\Container\Container;
use Illuminate\Filesystem\Filesystem;
use Shared\Elasticsearch\ElasticsearchIndexerInterface;

class ElasticsearchReindexCommand extends Command
{
    protected $signature = 'elasticsearch:reindex
        {--chunk=500 : Batch size per bulk request}';

    protected $description = 'Zero-downtime reindex: writes to {name}_new, atomically switches alias, removes old index.';

    public function __construct(
        private readonly Client $client,
        private readonly Filesystem $files,
    ) {
        parent::__construct();
    }

    public function handle(Container $container): int
    {
        $chunkSize = (int) $this->option('chunk');

        /** @var list<ElasticsearchIndexerInterface> $indexers */
        $indexers = $container->tagged('es-indexers');

        if (empty($indexers)) {
            $this->warn('No es-indexers registered. Tag implementations with "es-indexers" in a ServiceProvider.');

            return self::SUCCESS;
        }

        foreach ($indexers as $indexer) {
            $this->reindexOne($indexer, $chunkSize);
        }

        return self::SUCCESS;
    }

    private function reindexOne(ElasticsearchIndexerInterface $indexer, int $chunkSize): void
    {
        $alias = $indexer->getIndexName();
        $newIndex = "{$alias}_new";
        $mappingFile = database_path("elasticsearch/{$alias}.json");

        // Create new index with mapping
        $this->client->indices()->delete(['index' => $newIndex, 'ignore_unavailable' => true]);
        /** @var array<string, mixed> $body */
        $body = $this->files->exists($mappingFile)
            ? (array) json_decode($this->files->get($mappingFile), true)
            : [];
        $this->client->indices()->create(['index' => $newIndex, 'body' => $body]);
        $this->line("  <comment>Created:</comment> {$newIndex}");

        // Index from DB to new index
        $total = $indexer->indexToTarget($newIndex, $chunkSize);
        $this->line("  <info>Indexed:</info> {$total} records → {$newIndex}");

        // Determine the current concrete index behind the alias (if any)
        $oldIndex = $this->resolveCurrentIndex($alias);

        // Atomically switch alias to new index
        $actions = [];
        if ($oldIndex !== null) {
            $actions[] = ['remove' => ['index' => $oldIndex, 'alias' => $alias]];
        }
        $actions[] = ['add' => ['index' => $newIndex, 'alias' => $alias]];
        $this->client->indices()->updateAliases(['body' => ['actions' => $actions]]);
        $this->line("  <info>Alias:</info> {$alias} → {$newIndex}");

        // Remove old concrete index
        if ($oldIndex !== null) {
            $this->client->indices()->delete(['index' => $oldIndex]);
            $this->line("  <comment>Removed:</comment> {$oldIndex}");
        }
    }

    private function resolveCurrentIndex(string $alias): ?string
    {
        try {
            /** @var array<string, mixed> $response */
            $response = (array) $this->client->indices()->getAlias(['name' => $alias]);
            $indices = array_keys($response);

            return count($indices) > 0 ? $indices[0] : null;
        } catch (ClientResponseException $e) {
            if ($e->getResponse()->getStatusCode() !== 404) {
                throw $e;
            }

            // Alias does not exist — may be a plain index; check for it
            try {
                /** @var EsResponse $existsResponse */
                $existsResponse = $this->client->indices()->exists(['index' => $alias]);
                $exists = $existsResponse->asBool();

                return $exists ? $alias : null;
            } catch (ClientResponseException $inner) {
                if ($inner->getResponse()->getStatusCode() !== 404) {
                    throw $inner;
                }

                return null;
            }
        }
    }
}
