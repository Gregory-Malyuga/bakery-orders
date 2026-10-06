<?php

namespace Shared\Testing\Traits;

use PHPUnit\Framework\TestCase;

/**
 * @phpstan-require-extends TestCase
 */
trait MakesApiAssertions
{
    /**
     * @param  array<string, mixed>  $body
     */
    protected function assertApiPaginatedShape(array $body): void
    {
        $this->assertArrayHasKey('data', $body);
        $this->assertArrayHasKey('meta', $body);
        $this->assertArrayHasKey('links', $body);
        $this->assertIsArray($body['data']);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    protected function assertMetaHasCurrentPage(array $meta, int $page = 1): void
    {
        $this->assertArrayHasKey('current_page', $meta);
        $this->assertSame($page, $meta['current_page']);
    }
}
