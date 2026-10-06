<?php

namespace Tests\Unit\Shared\Elasticsearch;

use Domains\User\Domain\Models\User;
use Elastic\Elasticsearch\Client;
use Shared\Elasticsearch\ElasticsearchSearchable;
use Shared\Elasticsearch\EsTruncationBag;
use Shared\Elasticsearch\InteractsWithElasticsearch;
use Shared\QueryBuilder\BaseQueryBuilder;
use Shared\Repository\BaseRepository;
use Spatie\LaravelData\Data;
use Tests\TestCase;

class InteractsWithElasticsearchTest extends TestCase
{
    public function test_post_process_builder_skips_es_when_should_search_returns_false(): void
    {
        // Bind a spy that throws if search() is ever called
        $this->app->bind(Client::class, function () {
            return new class
            {
                /** @param array<string, mixed> $params */
                public function search(array $params): never
                {
                    throw new \RuntimeException('ES Client::search() must not be called when shouldSearch() returns false');
                }
            };
        });
        $this->app->instance(EsTruncationBag::class, new EsTruncationBag);

        $repo = new class extends BaseRepository implements ElasticsearchSearchable
        {
            use InteractsWithElasticsearch;

            protected string $model = User::class;

            public function shouldSearch(Data $filters): bool
            {
                return false;
            }

            public function buildEsQuery(Data $filters): array
            {
                return [];
            }

            public function callPostProcess(BaseQueryBuilder $qb, Data $filters): BaseQueryBuilder
            {
                return $this->postProcessBuilder($qb, $filters);
            }
        };

        $filters = new class extends Data {};
        $qb = $repo->newQuery();

        $result = $repo->callPostProcess($qb, $filters);

        $this->assertSame($qb, $result);
    }
}
