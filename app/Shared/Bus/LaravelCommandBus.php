<?php

namespace Shared\Bus;

use Illuminate\Contracts\Container\Container;
use RuntimeException;

readonly class LaravelCommandBus implements CommandBusInterface
{
    public function __construct(private Container $container) {}

    public function dispatch(BaseCommand $command): ?int
    {
        $commandClass = get_class($command);
        $handlerClass = preg_replace('/Command$/', 'Handler', $commandClass) ?? $commandClass;

        if ($handlerClass === $commandClass) {
            throw new RuntimeException("Command class must end with 'Command': {$commandClass}");
        }

        $handler = $this->container->make($handlerClass);

        if (! $handler instanceof CommandHandlerInterface) {
            throw new RuntimeException("Handler {$handlerClass} must implement ".CommandHandlerInterface::class);
        }

        return $handler->handle($command);
    }
}
