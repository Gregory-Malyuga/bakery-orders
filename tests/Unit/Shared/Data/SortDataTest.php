<?php

namespace Tests\Unit\Shared\Data;

use Illuminate\Http\Request;
use Shared\Data\SortData;
use Tests\TestCase;

class SortDataTest extends TestCase
{
    public function test_defaults(): void
    {
        $data = new SortData;

        $this->assertSame('id', $data->field);
        $this->assertSame('asc', $data->direction);
    }

    public function test_from_request_with_params(): void
    {
        $request = Request::create('/', 'GET', ['sort' => 'code', 'direction' => 'desc']);

        $data = SortData::fromRequest($request);

        $this->assertSame('code', $data->field);
        $this->assertSame('desc', $data->direction);
    }

    public function test_from_request_uses_defaults_when_absent(): void
    {
        $request = Request::create('/', 'GET');

        $data = SortData::fromRequest($request);

        $this->assertSame('id', $data->field);
        $this->assertSame('asc', $data->direction);
    }
}
