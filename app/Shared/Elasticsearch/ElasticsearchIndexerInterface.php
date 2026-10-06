<?php

namespace Shared\Elasticsearch;

interface ElasticsearchIndexerInterface
{
    public function getIndexName(): string;

    public function index(int $chunkSize): int;

    public function indexToTarget(string $targetIndex, int $chunkSize): int;

    /** @param array<int, int> $ids */
    public function indexByIds(array $ids): void;

    public function deleteFromIndex(int $id): void;
}
