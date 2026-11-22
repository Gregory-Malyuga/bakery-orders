<?php

namespace Domains\Invoice\Application\Queries\Batch;

use Domains\Invoice\Application\Data\InvoiceOrdersData;
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

    public function handle(object $message): InvoiceOrdersData
    {
        assert($message instanceof BatchInvoiceQuery);

        $orders = $this->orders->orders($message->date);

        return new InvoiceOrdersData([
            'orders' => $orders,
            'returns' => $this->returns->forOrders($orders),
            'groups' => $this->groups->forOrders($orders),
            'date' => $message->date,
            'generatedAt' => now('Europe/Moscow'),
        ]);
    }
}
