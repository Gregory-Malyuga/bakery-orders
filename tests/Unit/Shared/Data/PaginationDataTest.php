<?php

namespace Tests\Unit\Shared\Data;

use Illuminate\Http\Request;
use Shared\Data\PaginationData;
use Tests\TestCase;

class PaginationDataTest extends TestCase
{
    public function testDefaults(): void
    {
        $data = new PaginationData;

        $this->assertSame(1, $data->page);
        $this->assertSame(15, $data->perPage);
    }

    public function testFromRequestWithParams(): void
    {
        $request = Request::create('/', 'GET', ['page' => '3', 'per_page' => '50']);

        $data = PaginationData::fromRequest($request);

        $this->assertSame(3, $data->page);
        $this->assertSame(50, $data->perPage);
    }

    public function testFromRequestUsesDefaultsWhenAbsent(): void
    {
        $request = Request::create('/', 'GET');

        $data = PaginationData::fromRequest($request);

        $this->assertSame(1, $data->page);
        $this->assertSame(15, $data->perPage);
    }
}
