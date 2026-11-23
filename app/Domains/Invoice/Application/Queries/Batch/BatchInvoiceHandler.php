<?php

namespace Domains\Invoice\Application\Queries\Batch;

use Domains\Invoice\Application\Data\BatchInvoiceData;
use Domains\Order\Application\Repositories\OrderRepositoryInterface;
use Domains\OrderReturn\Application\OrderReturnSummary;
use Domains\StoreGroup\Application\GroupInvoiceService;
use Shared\Bus\QueryHandlerInterface;

readonly class BatchInvoiceHandler implements QueryHandlerInterface
{
    public function __construct(
        private OrderRepositoryInterface $orders,
        private OrderReturnSummary $returns,
        private GroupInvoiceService $groups,
    ) {}

    public function handle(object $message): BatchInvoiceData
    {
        assert($message instanceof BatchInvoiceQuery);

        $orders = $this->orders->orders($message->date);

        return new BatchInvoiceData($orders, $this->returns->forOrders($orders), $this->groups->forOrders($orders), $message->date, now('Europe/Moscow'));
    }
}
