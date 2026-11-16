<?php

namespace Domains\StockItem\Application\Commands\SyncCatalog;

use Domains\StockItem\Application\Repositories\StockItemRepositoryInterface;
use Shared\Bus\CommandHandlerInterface;

readonly class SyncCatalogHandler implements CommandHandlerInterface
{
    public function __construct(private StockItemRepositoryInterface $repository) {}

    public function handle(object $message): ?int
    {
        assert($message instanceof SyncCatalogCommand);

        $this->repository->syncCatalog();

        return null;
    }
}
