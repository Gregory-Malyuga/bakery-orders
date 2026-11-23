<?php

namespace Domains\Invoice\Application\Queries\Single;

use Domains\Invoice\Application\Data\SingleInvoiceData;
use Domains\Order\Application\Repositories\OrderRepositoryInterface;
use Domains\OrderReturn\Application\OrderReturnSummary;
use Illuminate\Database\Eloquent\Collection;
use Shared\Bus\QueryHandlerInterface;

readonly class SingleInvoiceHandler implements QueryHandlerInterface
{
    public function __construct(
        private OrderRepositoryInterface $orders,
        private OrderReturnSummary $returns,
    ) {}

    public function handle(object $message): SingleInvoiceData
    {
        assert($message instanceof SingleInvoiceQuery);

        $record = $this->orders->order($message->orderId);
        abort_unless($record !== null && $record->status === 'accepted', 404);

        return new SingleInvoiceData(
            new Collection([$record]),
            $this->returns->forOrders([$record]),
        );
    }
}
