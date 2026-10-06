<?php

namespace Domains\User\Infrastructure\Elasticsearch;

use Domains\User\Domain\Models\User;
use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\Exception\ClientResponseException;
use Elastic\Elasticsearch\Response\Elasticsearch;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Shared\Elasticsearch\ElasticsearchIndexerInterface;

class UserElasticsearchIndexer implements ElasticsearchIndexerInterface
{
    public function __construct(private readonly Client $client) {}

    public function getIndexName(): string
    {
        $token = getenv('TEST_TOKEN');

        return ($token !== false && $token !== '') ? "users_{$token}" : 'users';
    }

    public function index(int $chunkSize): int
    {
        return $this->indexToTarget($this->getIndexName(), $chunkSize);
    }

    public function indexToTarget(string $targetIndex, int $chunkSize): int
    {
        $total = 0;

        User::query()
            ->select(['id', 'email'])
            ->chunkById($chunkSize, function (Collection $users) use ($targetIndex, &$total): void {
                $this->bulkIndex($targetIndex, array_values($users->all()));
                $total += $users->count();
            });

        return $total;
    }

    /** @param array<int, int> $ids */
    public function indexByIds(array $ids): void
    {
        /** @var list<User> $users */
        $users = User::query()
            ->select(['id', 'email'])
            ->whereIn('id', $ids)
            ->get()
            ->all();

        if (empty($users)) {
            return;
        }

        $this->bulkIndex($this->getIndexName(), $users);
    }

    public function deleteFromIndex(int $id): void
    {
        try {
            $this->client->delete([
                'index' => $this->getIndexName(),
                'id' => (string) $id,
            ]);
        } catch (ClientResponseException $e) {
            if ($e->getResponse()->getStatusCode() !== 404) {
                throw $e;
            }
        }
    }

    public function refreshIndex(): void
    {
        $this->client->indices()->refresh(['index' => $this->getIndexName()]);
    }

    /** @param list<User> $users */
    private function bulkIndex(string $index, array $users): void
    {
        $body = [];

        foreach ($users as $user) {
            $body[] = ['index' => ['_index' => $index, '_id' => $user->id]];
            $body[] = ['email' => $user->email];
        }

        /** @var Elasticsearch $bulkResponse */
        $bulkResponse = $this->client->bulk(['body' => $body]);
        /** @var array{errors: bool, items: list<array{index: array{error?: mixed}}>} $response */
        $response = $bulkResponse->asArray();

        if ($response['errors']) {
            $failed = array_filter(
                $response['items'],
                fn (array $item): bool => isset($item['index']['error']),
            );

            Log::error('Elasticsearch bulk index errors', [
                'index' => $index,
                'failed_count' => count($failed),
                'errors' => array_column(array_column($failed, 'index'), 'error'),
            ]);
        }
    }
}
