<?php

namespace Tests\Unit\Shared\QueryBuilder;

use Domains\User\Domain\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Shared\QueryBuilder\BaseQueryBuilder;
use Tests\TestCase;

class BaseQueryBuilderTest extends TestCase
{
    public function test_returns_builder_instance(): void
    {
        $qb = new BaseQueryBuilder(User::class);

        $this->assertInstanceOf(Builder::class, $qb->getQuery());
    }

    public function test_fluent_methods_return_same_instance(): void
    {
        $qb = new BaseQueryBuilder(User::class);

        $this->assertSame($qb, $qb->select(['id', 'name']));
        $this->assertSame($qb, $qb->with([]));
        $this->assertSame($qb, $qb->where('id', 1));
        $this->assertSame($qb, $qb->whereIn('id', [1, 2]));
        $this->assertSame($qb, $qb->whereBetween('id', [1, 10]));
        $this->assertSame($qb, $qb->orderBy('name'));
    }

    public function test_select_is_applied_to_query(): void
    {
        $qb = new BaseQueryBuilder(User::class);
        $qb->select(['id', 'name']);

        $this->assertStringContainsString('"id", "name"', $qb->getQuery()->toRawSql());
    }
}
