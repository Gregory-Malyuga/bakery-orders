<?php

namespace Shared\Testing\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\TestCase;
use Shared\Data\PaginationData;
use Shared\Repository\BaseRepository;
use Spatie\LaravelData\Data;

/**
 * @phpstan-require-extends TestCase
 */
trait HasRepositoryTests
{
    use DatabaseTransactions;
    use InteractsWithPagination;
    use InteractsWithRepository;

    abstract protected function repository(): BaseRepository;

    /** @return array<string, mixed> */
    abstract protected function makeModelData(): array;

    /** @return array<string, mixed> */
    abstract protected function updateModelData(): array;

    protected function createRecord(): Model
    {
        return $this->repository()->create($this->makeModelData());
    }

    public function test_find_returns_model(): void
    {
        $model = $this->createRecord();
        /** @var int|string $key */
        $key = $model->getKey();

        $result = $this->repository()->find($key);

        $this->assertNotNull($result);
        $this->assertSame($key, $result->getKey());
    }

    public function test_find_returns_null_for_missing(): void
    {
        $this->assertNull($this->repository()->find(PHP_INT_MAX));
    }

    public function test_find_or_fail_returns_model(): void
    {
        $model = $this->createRecord();
        /** @var int|string $key */
        $key = $model->getKey();

        $result = $this->repository()->findOrFail($key);

        $this->assertSame($model->getKey(), $result->getKey());
    }

    public function test_find_or_fail_throws_for_missing(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->repository()->findOrFail(PHP_INT_MAX);
    }

    public function test_create_persists_and_returns_model(): void
    {
        $model = $this->repository()->create($this->makeModelData());

        $this->assertInstanceOf(Model::class, $model);
        $this->assertNotNull($model->getKey());
        /** @var int|string $key */
        $key = $model->getKey();
        $this->assertNotNull($this->repository()->find($key));
    }

    public function test_update_modifies_model(): void
    {
        $model = $this->createRecord();
        $data = $this->updateModelData();

        $updated = $this->repository()->update($model, $data);

        foreach ($data as $key => $value) {
            $this->assertEquals($value, $updated->$key);
        }
    }

    public function test_delete_removes_model(): void
    {
        $model = $this->createRecord();
        /** @var int|string $key */
        $key = $model->getKey();

        $this->repository()->delete($model);

        $this->assertNull($this->repository()->find($key));
    }

    public function test_list_returns_paginated_results(): void
    {
        $this->createRecord();
        $this->createRecord();

        $result = $this->repository()->list(
            new class extends Data {},
            null,
            new PaginationData(page: 1, perPage: 10),
        );

        $this->assertGreaterThanOrEqual(2, $result->total());
        $this->assertPaginatorPerPage(10, $result);
    }
}
