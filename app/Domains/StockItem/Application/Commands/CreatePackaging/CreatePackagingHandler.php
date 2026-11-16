<?php

namespace Domains\StockItem\Application\Commands\CreatePackaging;

use Domains\StockItem\Application\Repositories\StockItemRepositoryInterface;
use Shared\Bus\CommandHandlerInterface;

readonly class CreatePackagingHandler implements CommandHandlerInterface
{
    public function __construct(private StockItemRepositoryInterface $repository) {}

    public function handle(object $message): ?int
    {
        assert($message instanceof CreatePackagingCommand);

        $this->repository->createPackaging($message->name, $message->unit);

        return null;
    }
}
