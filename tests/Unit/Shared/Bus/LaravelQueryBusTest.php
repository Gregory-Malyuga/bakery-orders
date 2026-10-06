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

    public function test_asks_query_to_resolved_handler(): void
    {
        $this->container->bind(FindUserByIdHandler::class, fn () => new FindUserByIdHandler);

        $result = $this->bus->ask(new FindUserByIdQuery(42));

        $this->assertInstanceOf(\stdClass::class, $result);
        $this->assertSame(42, $result->id);
    }

    public function test_resolves_handler_by_replacing_query_suffix(): void
    {
        $handler = new FindUserByIdHandler;
        $this->container->bind(FindUserByIdHandler::class, fn () => $handler);

        $this->bus->ask(new FindUserByIdQuery(1));

        $this->assertTrue($handler->called);
    }

    public function test_throws_when_class_does_not_end_with_query(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches("/must end with 'Query'/");

        $this->bus->ask(new class implements BaseQuery {});
    }

    public function test_throws_when_handler_does_not_implement_handler_interface(): void
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
