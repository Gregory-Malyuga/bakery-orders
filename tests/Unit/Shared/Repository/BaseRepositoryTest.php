<?php

namespace Tests\Unit\Shared\Repository;

use Domains\User\Domain\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Shared\Data\PaginationData;
use Shared\Data\SortData;
use Shared\QueryBuilder\BaseQueryBuilder;
use Shared\Repository\BaseRepository;
use Spatie\LaravelData\Data;
use Tests\TestCase;
use TypeError;

class BaseRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    private BaseRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repo = new class extends BaseRepository
        {
            protected string $model = User::class;
        };
    }

    // ── find ──────────────────────────────────────────────────────────────────

    public function testFindReturnsModel(): void
    {
        $user = User::factory()->create(['name' => 'Alice']);

        $result = $this->repo->find($user->id);

        $this->assertInstanceOf(User::class, $result);
        $this->assertSame($user->id, $result->id);
    }

    public function testFindReturnsNullWhenMissing(): void
    {
        $this->assertNull($this->repo->find(999));
    }

    // ── findOrFail ────────────────────────────────────────────────────────────

    public function testFindOrFailReturnsModel(): void
    {
        $user = User::factory()->create(['name' => 'Bob']);

        $result = $this->repo->findOrFail($user->id);

        $this->assertSame($user->id, $result->id);
    }

    public function testFindOrFailThrowsModelNotFound(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->repo->findOrFail(999);
    }

    // ── create ────────────────────────────────────────────────────────────────

    public function testCreatePersistsAndReturnsModel(): void
    {
        $user = $this->repo->create(['name' => 'Charlie', 'email' => 'charlie@example.com', 'password' => 'secret123']);

        $this->assertInstanceOf(User::class, $user);
        $this->assertDatabaseHas('users', ['name' => 'Charlie']);
    }

    // ── update ────────────────────────────────────────────────────────────────

    public function testUpdateModifiesModel(): void
    {
        $user = User::factory()->create(['name' => 'Dave']);

        $updated = $this->repo->update($user, ['name' => 'David']);

        $this->assertSame('David', $updated->name);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'David']);
    }

    // ── delete ────────────────────────────────────────────────────────────────

    public function testDeleteRemovesModel(): void
    {
        $user = User::factory()->create(['name' => 'Eve']);

        $this->repo->delete($user);

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    // ── list ──────────────────────────────────────────────────────────────────

    public function testListReturnsPaginator(): void
    {
        $emptyFilters = new class extends Data {};

        $result = $this->repo->list($emptyFilters);

        $this->assertInstanceOf(LengthAwarePaginator::class, $result);
    }

    public function testListReturnsAllRecords(): void
    {
        User::factory()->create(['name' => 'Frank']);
        User::factory()->create(['name' => 'Grace']);

        $emptyFilters = new class extends Data {};
        $result = $this->repo->list($emptyFilters, null, new PaginationData(1, 50));

        $this->assertSame(2, $result->total());
    }

    public function testListRespectsPagination(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            User::factory()->create(['name' => "User {$i}"]);
        }

        $emptyFilters = new class extends Data {};
        $result = $this->repo->list($emptyFilters, null, new PaginationData(1, 2));

        $this->assertSame(2, $result->perPage());
        $this->assertSame(5, $result->total());
        $this->assertCount(2, $result->items());
    }

    public function testListRespectsSort(): void
    {
        User::factory()->create(['name' => 'Zara']);
        User::factory()->create(['name' => 'Apple']);

        $emptyFilters = new class extends Data {};
        $result = $this->repo->list($emptyFilters, new SortData('name', 'asc'), new PaginationData(1, 50));

        $this->assertSame('Apple', $result->items()[0]->name);
    }

    // ── newQuery ──────────────────────────────────────────────────────────────

    public function testNewQueryReturnsQueryBuilder(): void
    {
        $this->assertInstanceOf(BaseQueryBuilder::class, $this->repo->newQuery());
    }

    // ── applyBaseFilters hook ─────────────────────────────────────────────────

    public function testApplyBaseFiltersConstrainsList(): void
    {
        User::factory()->create(['name' => 'Alice']);
        User::factory()->create(['name' => 'Bob']);

        $repo = new class extends BaseRepository
        {
            protected string $model = User::class;

            protected function applyBaseFilters(BaseQueryBuilder $qb): BaseQueryBuilder
            {
                return $qb->where('name', 'Alice');
            }
        };

        $result = $repo->list(new class extends Data {}, null, new PaginationData(1, 50));

        $this->assertSame(1, $result->total());
        $this->assertSame('Alice', $result->items()[0]->name);
    }

    // ── boot() type-safety ────────────────────────────────────────────────────

    public function testBootThrowsOnInvalidQueryBuilder(): void
    {
        $repo = new class extends BaseRepository
        {
            protected string $model = User::class;

            protected string $queryBuilder = \stdClass::class;
        };

        $this->expectException(TypeError::class);

        $repo->list(new class extends Data {});
    }
}
