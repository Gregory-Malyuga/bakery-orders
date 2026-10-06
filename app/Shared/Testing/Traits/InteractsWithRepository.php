<?php

namespace Shared\Testing\Traits;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use PHPUnit\Framework\TestCase;

/**
 * @phpstan-require-extends TestCase
 */
trait InteractsWithRepository
{
    /**
     * @template T
     *
     * @param  LengthAwarePaginator<int, T>  $paginator
     */
    protected function assertPaginatorTotal(int $expected, LengthAwarePaginator $paginator): void
    {
        $this->assertSame($expected, $paginator->total());
    }

    /**
     * @template T
     *
     * @param  LengthAwarePaginator<int, T>  $paginator
     */
    protected function assertPaginatorPerPage(int $expected, LengthAwarePaginator $paginator): void
    {
        $this->assertSame($expected, $paginator->perPage());
    }

    /**
     * @template T
     *
     * @param  LengthAwarePaginator<int, T>  $paginator
     */
    protected function assertPaginatorCount(int $expected, LengthAwarePaginator $paginator): void
    {
        $this->assertCount($expected, $paginator->items());
    }
}
