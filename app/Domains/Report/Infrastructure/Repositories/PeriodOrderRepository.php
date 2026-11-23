<?php

namespace Domains\Report\Infrastructure\Repositories;

use Domains\Order\Domain\Models\Order;
use Domains\Report\Application\Repositories\PeriodOrderRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

final class PeriodOrderRepository implements PeriodOrderRepositoryInterface
{
    /** @return Collection<int, Order> */
    public function forPeriod(string $from, string $to): Collection
    {
        return Order::query()
            ->with('items')
            ->whereBetween('delivery_date', [$from, $to])
            ->where('status', 'accepted')
            ->orderBy('delivery_date')
            ->orderBy('bakery_id')
            ->get();
    }
}
