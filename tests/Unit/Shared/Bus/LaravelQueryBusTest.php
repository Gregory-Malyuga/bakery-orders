<?php

namespace Tests\Unit\Shared\Bus;

use Illuminate\Container\Container;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Bus\BaseQuery;
use Shared\Bus\LaravelQueryBus;
use Shared\Bus\QueryHandlerInterface;

class LaravelQueryBusTest extends TestCase
{
    private Container $container;

    private LaravelQueryBus $bus;

    protected function setUp(): void
    {
        $this->container = new Container;
        $this->bus = new LaravelQueryBus($this->container);
    }

    public function testAsksQueryToResolvedHandler(): void
    {
        $this->container->bind(FindUserByIdHandler::class, fn () => new FindUserByIdHandler);

        $result = $this->bus->ask(new FindUserByIdQuery(42));

        $this->assertInstanceOf(\stdClass::class, $result);
        $this->assertSame(42, $result->id);
    }

    public function testResolvesHandlerByReplacingQuerySuffix(): void
    {
        $handler = new FindUserByIdHandler;
        $this->container->bind(FindUserByIdHandler::class, fn () => $handler);

        $this->bus->ask(new FindUserByIdQuery(1));

        $this->assertTrue($handler->called);
    }

    public function testThrowsWhenClassDoesNotEndWithQuery(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches("/must end with 'Query'/");

        $this->bus->ask(new class implements BaseQuery {});
    }

    public function testThrowsWhenHandlerDoesNotImplementHandlerInterface(): void
    {
        $this->container->bind(FindUserByIdHandler::class, fn () => new \stdClass);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/must implement/');

        $this->bus->ask(new FindUserByIdQuery(1));
    }
}

class FindUserByIdQuery implements BaseQuery
{
    public function __construct(public readonly int $id) {}
}

class FindUserByIdHandler implements QueryHandlerInterface
{
    public bool $called = false;

    public function handle(object $message): object
    {
        $this->called = true;

        $result = new \stdClass;
        $result->id = $message instanceof FindUserByIdQuery ? $message->id : 0;

        return $result;
    }
}
