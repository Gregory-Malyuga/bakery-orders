<?php

namespace Domains\StockItem\Application\Queries\Dashboard;

use Domains\StockItem\Application\Data\StockDashboardData;
use Domains\StockItem\Application\Repositories\StockDashboardReadRepositoryInterface;
use Shared\Bus\QueryHandlerInterface;

readonly class StockDashboardHandler implements QueryHandlerInterface
{
    public function __construct(private StockDashboardReadRepositoryInterface $repository) {}

    public function handle(object $message): StockDashboardData
    {
        assert($message instanceof StockDashboardQuery);

        return new StockDashboardData($this->repository->data($message->date));
    }
}
