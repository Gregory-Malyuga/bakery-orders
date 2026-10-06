<?php

namespace Domains\User\Tests\Unit;

use Domains\User\Application\Data\UserFilterData;
use Domains\User\Domain\Models\User;
use Domains\User\Infrastructure\Elasticsearch\UserElasticsearchIndexer;
use Domains\User\Infrastructure\Repositories\UserRepository;
use Shared\Repository\BaseRepository;
use Shared\Testing\BaseRepositoryTest;
use Shared\Testing\Traits\RefreshElasticsearch;

class UserRepositoryTest extends BaseRepositoryTest
{
    use RefreshElasticsearch;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpElasticsearchIndex($this->esIndex());
    }

    protected function tearDown(): void
    {
        $this->tearDownElasticsearchIndex($this->esIndex());
        parent::tearDown();
    }

    private function esIndex(): string
    {
        return app(UserElasticsearchIndexer::class)->getIndexName();
    }

    protected function repository(): BaseRepository
    {
        return new UserRepository;
    }

    /** @return array<string, mixed> */
    protected function makeModelData(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'secret1234',
            'role' => 'user',
        ];
    }

    /** @return array<string, mixed> */
    protected function updateModelData(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
        ];
    }

    public function test_update_modifies_model(): void
    {
        $model = $this->repository()->create($this->makeModelData());
        $data = $this->updateModelData();

        /** @var User $updated */
        $updated = $this->repository()->update($model, $data);

        $this->assertSame($data['name'], $updated->name);
        $this->assertSame($data['email'], $updated->email);
    }

    public function test_list_filters_by_name(): void
    {
        $this->repository()->create(['name' => 'Alice', 'email' => 'alice@example.com', 'password' => 'pass1234', 'role' => 'user']);
        $this->repository()->create(['name' => 'Bob', 'email' => 'bob@example.com', 'password' => 'pass1234', 'role' => 'user']);

        $filters = new UserFilterData(name: 'Alice');
        $result = $this->repository()->list($filters);

        $this->assertSame(1, $result->total());
    }

    public function test_list_filters_by_email(): void
    {
        $alice = $this->repository()->create(['name' => 'Alice', 'email' => 'alice@example.com', 'password' => 'pass1234', 'role' => 'user']);
        $this->repository()->create(['name' => 'Bob', 'email' => 'bob@example.com', 'password' => 'pass1234', 'role' => 'user']);

        /** @var int $aliceId */
        $aliceId = $alice->getKey();
        $indexer = app(UserElasticsearchIndexer::class);
        $indexer->indexByIds([$aliceId]);
        $indexer->refreshIndex();

        $filters = new UserFilterData(email: 'alice@example.com');
        $result = $this->repository()->list($filters);

        $this->assertSame(1, $result->total());
    }

    public function test_list_filters_by_role(): void
    {
        $this->repository()->create(['name' => 'Alice', 'email' => 'alice@example.com', 'password' => 'pass1234', 'role' => 'admin']);
        $this->repository()->create(['name' => 'Bob', 'email' => 'bob@example.com', 'password' => 'pass1234', 'role' => 'user']);

        $filters = new UserFilterData(role: 'admin');
        $result = $this->repository()->list($filters);

        $this->assertSame(1, $result->total());
    }
}
