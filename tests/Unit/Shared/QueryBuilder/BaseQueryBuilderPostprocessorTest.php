<?php

namespace Tests\Unit\Shared\QueryBuilder;

use Domains\User\Domain\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Shared\Data\PaginationData;
use Shared\Data\SortData;
use Shared\QueryBuilder\BaseQueryBuilder;
use Shared\QueryBuilder\BaseQueryBuilderPostprocessor;
use Tests\TestCase;

class BaseQueryBuilderPostprocessorTest extends TestCase
{
    use DatabaseTransactions;

    private function createUsers(int $count): void
    {
        for ($i = 1; $i <= $count; $i++) {
            User::factory()->create(['name' => "User {$i}"]);
        }
    }

    public function test_returns_paginator(): void
    {
        $query = (new BaseQueryBuilder(User::class))->getQuery();
        $result = (new BaseQueryBuilderPostprocessor)->process(
            $query,
            new SortData,
            new PaginationData,
        );

        $this->assertInstanceOf(LengthAwarePaginator::class, $result);
    }

    public function test_respects_per_page(): void
    {
        $this->createUsers(5);

        $query = (new BaseQueryBuilder(User::class))->getQuery();
        $result = (new BaseQueryBuilderPostprocessor)->process(
            $query,
            new SortData,
            new PaginationData(1, 2),
        );

        $this->assertSame(2, $result->perPage());
        $this->assertSame(5, $result->total());
        $this->assertCount(2, $result->items());
    }

    public function test_respects_page(): void
    {
        $this->createUsers(5);

        $query = (new BaseQueryBuilder(User::class))->getQuery();
        $result = (new BaseQueryBuilderPostprocessor)->process(
            $query,
            new SortData,
            new PaginationData(2, 2),
        );

        $this->assertSame(2, $result->currentPage());
        $this->assertCount(2, $result->items());
    }
}
