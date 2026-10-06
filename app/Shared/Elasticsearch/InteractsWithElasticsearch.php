<?php

namespace Shared\Elasticsearch;

use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\Response\Elasticsearch as EsResponse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Shared\QueryBuilder\BaseQueryBuilder;
use Shared\Repository\BaseRepository;
use Spatie\LaravelData\Data;

trait InteractsWithElasticsearch
{
    /** @phpstan-require-extends BaseRepository */
    /** @phpstan-require-implements ElasticsearchSearchable */

    /**
     * @param  BaseQueryBuilder<Model>  $qb
     * @return BaseQueryBuilder<Model>
     */
    protected function postProcessBuilder(BaseQueryBuilder $qb, Data $filters): BaseQueryBuilder
    {
        if (! $this->shouldSearch($filters)) {
            return $qb;
        }

        $ids = $this->fetchIdsFromElasticsearch($filters);

        return $qb->whereIn('id', $ids);
    }

    /**
     * Executes an ES search via buildEsQuery(), returns extracted _id values.
     * Empty array → Eloquent adds WHERE 0=1 → zero results without extra logic.
     * Sets EsTruncationBag and logs a warning when total > returned hits.
     *
     * @return array<int|string>
     */
    protected function fetchIdsFromElasticsearch(Data $filters): array
    {
        /** @var Client $client */
        $client = app(Client::class);

        $table = (new $this->model)->getTable();
        $token = getenv('TEST_TOKEN');
        $index = ($token !== false && $token !== '') ? "{$table}_{$token}" : $table;

        /** @var EsResponse $response */
        $response = $client->search([
            'index' => $index,
            'body' => $this->buildEsQuery($filters),
        ]);

        /** @var array{hits: array{total: array{value: int}, hits: list<array{_id: string}>}} $data */
        $data = $response->asArray();
        $hits = $data['hits']['hits'];
        $total = $data['hits']['total']['value'];
        $ids = array_column($hits, '_id');

        if ($total > count($ids)) {
            /** @var EsTruncationBag $bag */
            $bag = app(EsTruncationBag::class);
            $bag->markTruncated();
            Log::warning('Elasticsearch result truncated', [
                'index' => $index,
                'total' => $total,
                'returned' => count($ids),
            ]);
        }

        return $ids;
    }
}
