<?php

namespace Domains\Order\Application\Queries\Detail;

use Domains\Order\Application\Data\AdminOrderViewData;
use Domains\Order\Application\Repositories\OrderRepositoryInterface;
use Domains\OrderAdjustment\Application\Repositories\OrderAdjustmentReadRepositoryInterface;
use Domains\OrderReturn\Application\OrderReturnSummary;
use Domains\OrderReturn\Application\Repositories\OrderReturnReadRepositoryInterface;
use Shared\Bus\QueryHandlerInterface;

readonly class AdminOrderDetailHandler implements QueryHandlerInterface
{
    public function __construct(
        private OrderRepositoryInterface $orders,
        private OrderReturnReadRepositoryInterface $returns,
        private OrderAdjustmentReadRepositoryInterface $adjustments,
        private OrderReturnSummary $summary,
    ) {}

    public function handle(object $message): AdminOrderViewData
    {
        assert($message instanceof AdminOrderDetailQuery);

        $record = $this->orders->order($message->orderId);
        abort_if($record === null, 404);

        $prices = $this->orders->prices((int) $record->bakery_id);
        $returnProducts = $this->orders->products()->filter(
            fn ($product): bool => $record->items->contains('product_id', $product->id)
                || ($product->is_active && isset($prices[$product->id])),
        );
        $returnAmount = $this->summary->forOrders([$record])[$message->orderId]['amount'] ?? 0;

        return new AdminOrderViewData([
            'order' => $record,
            'returns' => $this->returns->historyForOrder($message->orderId),
            'adjustments' => $this->adjustments->history($message->orderId),
            'returnProducts' => $returnProducts,
            'returnAmount' => $returnAmount,
        ]);
    }
}
