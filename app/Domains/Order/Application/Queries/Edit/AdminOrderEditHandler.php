<?php

namespace Domains\Order\Application\Queries\Edit;

use Domains\Order\Application\Data\AdminOrderViewData;
use Domains\Order\Application\Repositories\OrderRepositoryInterface;
use Shared\Bus\QueryHandlerInterface;

readonly class AdminOrderEditHandler implements QueryHandlerInterface
{
    public function __construct(private OrderRepositoryInterface $orders) {}

    public function handle(object $message): AdminOrderViewData
    {
        assert($message instanceof AdminOrderEditQuery);

        $record = $this->orders->order($message->orderId);
        abort_unless($record !== null && $record->status === 'accepted', 404);

        $prices = $this->orders->prices((int) $record->bakery_id);
        $products = $this->orders->products()->filter(
            fn ($product): bool => $record->items->contains('product_id', $product->id)
                || ($product->is_active && isset($prices[$product->id])),
        );

        return new AdminOrderViewData(['order' => $record, 'products' => $products, 'prices' => $prices]);
    }
}
