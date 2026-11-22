<?php

namespace Tests\Unit\Shared\Http\Data;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Tests\TestCase;

class BaseDataTest extends TestCase
{
    public function testCanBeInstantiatedWithProperties(): void
    {
        $data = new StubData(name: 'test', value: 42);

        $this->assertSame('test', $data->name);
        $this->assertSame(42, $data->value);
    }

    public function testFromCreatesInstanceFromArray(): void
    {
        $data = StubData::from(['name' => 'hello', 'value' => 7]);

        $this->assertSame('hello', $data->name);
        $this->assertSame(7, $data->value);
    }

    public function testFromCreatesInstanceFromRequest(): void
    {
        $request = Request::create('/', 'GET', ['name' => 'req', 'value' => 3]);

        $data = StubData::from($request);

        $this->assertSame('req', $data->name);
        $this->assertSame(3, $data->value);
    }

    public function testToArraySerializesAllProperties(): void
    {
        $data = new StubData(name: 'arr', value: 99);

        $this->assertSame(['name' => 'arr', 'value' => 99], $data->toArray());
    }

    public function testToResponseReturnsJsonResponse(): void
    {
        $data = new StubData(name: 'resp', value: 1);
        $request = Request::create('/', 'GET');

        $response = $data->toResponse($request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['name' => 'resp', 'value' => 1], $response->getData(true));
    }

    public function testToResponseReturns201OnPost(): void
    {
        $data = new StubData(name: 'post', value: 2);
        $request = Request::create('/', 'POST');

        $response = $data->toResponse($request);

        $this->assertSame(201, $response->getStatusCode());
    }
}
