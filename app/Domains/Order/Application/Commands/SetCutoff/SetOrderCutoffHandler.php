<?php

namespace Domains\Order\Application\Commands\SetCutoff;

use Domains\Order\Application\Repositories\OrderCutoffRepositoryInterface;
use Shared\Bus\CommandHandlerInterface;

readonly class SetOrderCutoffHandler implements CommandHandlerInterface
{
    public function __construct(private OrderCutoffRepositoryInterface $repository) {}

    public function handle(object $message): ?int
    {
        assert($message instanceof SetOrderCutoffCommand);

        $this->repository->save($message->time);

        return null;
    }
}
