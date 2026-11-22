<?php

namespace Tests\Unit\Shared\Data;

use Illuminate\Http\Request;
use Shared\Data\SortData;
use Tests\TestCase;

class SortDataTest extends TestCase
{
    public function testDefaults(): void
    {
        $data = new SortData;

        $this->assertSame('id', $data->field);
        $this->assertSame('asc', $data->direction);
    }

    public function testFromRequestWithParams(): void
    {
        $request = Request::create('/', 'GET', ['sort' => 'code', 'direction' => 'desc']);

        $data = SortData::fromRequest($request);

        $this->assertSame('code', $data->field);
        $this->assertSame('desc', $data->direction);
    }

    public function testFromRequestUsesDefaultsWhenAbsent(): void
    {
        $request = Request::create('/', 'GET');

        $data = SortData::fromRequest($request);

        $this->assertSame('id', $data->field);
        $this->assertSame('asc', $data->direction);
    }
}
