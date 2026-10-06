<?php

namespace Tests\Unit\Shared\Http\Data;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Shared\Http\Data\BaseDataCollection;
use Spatie\LaravelData\PaginatedDataCollection;
use Tests\TestCase;

class BaseDataCollectionTest extends TestCase
{
    public function test_from_paginator_returns_paginated_data_collection(): void
    {
        $paginator = $this->makePaginator([['name' => 'a', 'value' => 1]]);

        $collection = BaseDataCollection::fromPaginator(StubData::class, $paginator);

        $this->assertInstanceOf(PaginatedDataCollection::class, $collection);
    }

    public function test_from_paginator_wraps_items_as_data_objects(): void
    {
        $paginator = $this->makePaginator([
            ['name' => 'x', 'value' => 10],
            ['name' => 'y', 'value' => 20],
        ]);

        $collection = BaseDataCollection::fromPaginator(StubData::class, $paginator);

        $items = iterator_to_array($collection);
        $this->assertCount(2, $items);
        $this->assertInstanceOf(StubData::class, $items[0]);
        $this->assertSame('x', $items[0]->name);
    }

    public function test_to_response_returns_json_with_data_meta_links(): void
    {
        $paginator = $this->makePaginator([['name' => 'n', 'value' => 5]], total: 1);

        $collection = BaseDataCollection::fromPaginator(StubData::class, $paginator);
        $response = $collection->toResponse(Request::create('/'));

        $this->assertInstanceOf(JsonResponse::class, $response);

        $body = $response->getData(true);
        $this->assertArrayHasKey('data', $body);
        $this->assertArrayHasKey('meta', $body);
        $this->assertArrayHasKey('links', $body);
    }

    public function test_to_response_data_contains_serialized_items(): void
    {
        $paginator = $this->makePaginator([['name' => 'item', 'value' => 7]], total: 1);

        $collection = BaseDataCollection::fromPaginator(StubData::class, $paginator);
        $body = $collection->toResponse(Request::create('/'))->getData(true);

        $this->assertSame([['name' => 'item', 'value' => 7]], $body['data']);
    }

    public function test_can_be_used_as_regular_data_collection(): void
    {
        $collection = new BaseDataCollection(StubData::class, [
            new StubData('a', 1),
            new StubData('b', 2),
        ]);

        $this->assertCount(2, $collection);
    }

    /** @param array<int, array<string, mixed>> $items */
    private function makePaginator(array $items, int $total = 10): LengthAwarePaginator
    {
        return new LengthAwarePaginator(
            items: $items,
            total: $total,
            perPage: 15,
            currentPage: 1,
        );
    }
}
