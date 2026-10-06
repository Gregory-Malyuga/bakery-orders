<?php

namespace Shared\Bus;

use Illuminate\Contracts\Container\Container;
use RuntimeException;

class LaravelQueryBus implements QueryBusInterface
{
    public function __construct(private readonly Container $container) {}

    public function ask(BaseQuery $query): object
    {
        $queryClass = get_class($query);
        $handlerClass = preg_replace('/Query$/', 'Handler', $queryClass) ?? $queryClass;

        if ($handlerClass === $queryClass) {
            throw new RuntimeException("Query class must end with 'Query': {$queryClass}");
        }

        $handler = $this->container->make($handlerClass);

        if (! $handler instanceof QueryHandlerInterface) {
            throw new RuntimeException("Handler {$handlerClass} must implement ".QueryHandlerInterface::class);
        }

        return $handler->handle($query);
    }
}
