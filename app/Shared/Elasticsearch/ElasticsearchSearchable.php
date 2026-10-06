<?php

namespace Shared\Elasticsearch;

use Spatie\LaravelData\Data;

interface ElasticsearchSearchable
{
    /** @return array<string, mixed> */
    public function buildEsQuery(Data $filters): array;

    public function shouldSearch(Data $filters): bool;
}
