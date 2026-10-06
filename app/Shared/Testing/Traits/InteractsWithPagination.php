<?php

namespace Shared\Testing\Traits;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use PHPUnit\Framework\TestCase;

/**
 * @phpstan-require-extends TestCase
 */
trait InteractsWithPagination
{
    /**
     * @param  array<string, mixed>  $body
     */
    protected function assertPaginatedResponse(array $body): void
    {
        $this->assertArrayHasKey('data', $body);
        $this->assertArrayHasKey('meta', $body);
        $this->assertArrayHasKey('links', $body);
    }

    /**
     * @param  LengthAwarePaginator<int, mixed>  $paginator
     */
    protected function assertPaginatorHas(int $total, int $perPage, LengthAwarePaginator $paginator): void
    {
        $this->assertSame($total, $paginator->total());
        $this->assertSame($perPage, $paginator->perPage());
    }
}
