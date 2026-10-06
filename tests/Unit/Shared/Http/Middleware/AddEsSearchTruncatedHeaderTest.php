<?php

namespace Tests\Unit\Shared\Http\Middleware;

use Illuminate\Http\Request;
use Shared\Elasticsearch\EsTruncationBag;
use Shared\Http\Middleware\AddEsSearchTruncatedHeader;
use Tests\TestCase;

class AddEsSearchTruncatedHeaderTest extends TestCase
{
    public function test_adds_header_when_truncated(): void
    {
        $bag = new EsTruncationBag;
        $bag->markTruncated();
        $this->app->instance(EsTruncationBag::class, $bag);

        $middleware = new AddEsSearchTruncatedHeader;
        $request = Request::create('/api/v1/users', 'GET');

        $response = $middleware->handle($request, fn () => response()->json([]));

        $this->assertSame('true', $response->headers->get('X-Search-Truncated'));
    }

    public function test_does_not_add_header_when_not_truncated(): void
    {
        $this->app->instance(EsTruncationBag::class, new EsTruncationBag);

        $middleware = new AddEsSearchTruncatedHeader;
        $request = Request::create('/api/v1/users', 'GET');

        $response = $middleware->handle($request, fn () => response()->json([]));

        $this->assertFalse($response->headers->has('X-Search-Truncated'));
    }

    public function test_does_not_reset_bag_after_response(): void
    {
        $bag = new EsTruncationBag;
        $bag->markTruncated();
        $this->app->instance(EsTruncationBag::class, $bag);

        $middleware = new AddEsSearchTruncatedHeader;
        $request = Request::create('/api/v1/users', 'GET');

        $middleware->handle($request, fn () => response()->json([]));

        // Reset is handled by Octane flush (config/octane.php), not by middleware.
        $this->assertTrue($bag->isTruncated());
    }
}
