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

    public function testFindReturnsModel(): void
    {
        $model = $this->createRecord();
        /** @var int|string $key */
        $key = $model->getKey();

        $result = $this->repository()->find($key);

        $this->assertNotNull($result);
        $this->assertSame($key, $result->getKey());
    }

    public function testFindReturnsNullForMissing(): void
    {
        $this->assertNull($this->repository()->find(PHP_INT_MAX));
    }

    public function testFindOrFailReturnsModel(): void
    {
        $model = $this->createRecord();
        /** @var int|string $key */
        $key = $model->getKey();

        $result = $this->repository()->findOrFail($key);

        $this->assertSame($model->getKey(), $result->getKey());
    }

    public function testFindOrFailThrowsForMissing(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->repository()->findOrFail(PHP_INT_MAX);
    }

    public function testCreatePersistsAndReturnsModel(): void
    {
        $model = $this->repository()->create($this->makeModelData());

        $this->assertInstanceOf(Model::class, $model);
        $this->assertNotNull($model->getKey());
        /** @var int|string $key */
        $key = $model->getKey();
        $this->assertNotNull($this->repository()->find($key));
    }

    public function testUpdateModifiesModel(): void
    {
        $model = $this->createRecord();
        $data = $this->updateModelData();

        $updated = $this->repository()->update($model, $data);

        foreach ($data as $key => $value) {
            $this->assertEquals($value, $updated->$key);
        }
    }

    public function testDeleteRemovesModel(): void
    {
        $model = $this->createRecord();
        /** @var int|string $key */
        $key = $model->getKey();

        $this->repository()->delete($model);

        $this->assertNull($this->repository()->find($key));
    }

    public function testListReturnsPaginatedResults(): void
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
