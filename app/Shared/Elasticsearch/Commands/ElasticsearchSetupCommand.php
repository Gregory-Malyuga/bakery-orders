<?php

namespace Shared\Elasticsearch\Commands;

use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\Response\Elasticsearch as EsResponse;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

class ElasticsearchSetupCommand extends Command
{
    protected $signature = 'elasticsearch:setup {--force : Drop and recreate existing indices}';

    protected $description = 'Create Elasticsearch indices with mappings from database/elasticsearch/*.json.';

    public function __construct(
        private readonly Client $client,
        private readonly Filesystem $files,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $mappingDir = database_path('elasticsearch');
        $mappingFiles = $this->files->glob("{$mappingDir}/*.json");

        if (empty($mappingFiles)) {
            $this->warn('No mapping files found in database/elasticsearch/');

            return self::SUCCESS;
        }

        foreach ($mappingFiles as $file) {
            /** @var string $file */
            $index = basename($file, '.json');
            $this->setupIndex($index, $file);
        }

        return self::SUCCESS;
    }

    private function setupIndex(string $index, string $mappingFile): void
    {
        /** @var array<string, mixed> $body */
        $body = (array) json_decode($this->files->get($mappingFile), true);

        /** @var EsResponse $existsResponse */
        $existsResponse = $this->client->indices()->exists(['index' => $index]);
        $exists = $existsResponse->asBool();

        if ($exists) {
            if (! $this->option('force')) {
                $this->line("  <comment>Skipped:</comment> {$index} (already exists, use --force to recreate)");

                return;
            }

            $this->client->indices()->delete(['index' => $index]);
            $this->line("  <comment>Dropped:</comment> {$index}");
        }

        $this->client->indices()->create(['index' => $index, 'body' => $body]);
        $this->line("  <info>Created:</info> {$index}");
    }
}
