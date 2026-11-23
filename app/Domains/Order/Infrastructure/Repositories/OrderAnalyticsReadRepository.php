<?php

namespace Domains\Order\Infrastructure\Repositories;

use Domains\Order\Application\Repositories\OrderAnalyticsReadRepositoryInterface;
use Domains\Order\Domain\Models\Order;
use Illuminate\Database\Eloquent\Collection;

final class OrderAnalyticsReadRepository implements OrderAnalyticsReadRepositoryInterface
{
    /** @return Collection<int, Order> */
    public function acceptedOrdersBetween(string $from, string $to, ?int $storeId): Collection
    {
        $query = Order::query()
            ->with('items')
            ->whereBetween('delivery_date', [$from, $to])
            ->where('status', 'accepted');

        if ($storeId !== null) {
            $query->where('bakery_id', $storeId);
        }

        return $query->orderBy('delivery_date')->orderBy('bakery_id')->get();
    }
}
