<?php

namespace Domains\Order\Infrastructure\Repositories;

use Domains\Order\Application\Repositories\OrderCalendarRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class OrderCalendarRepository implements OrderCalendarRepositoryInterface
{
    public function acceptedCountOn(string $deliveryDate): int
    {
        return DB::table('bakery_orders')->where('delivery_date', $deliveryDate)
            ->where('status', 'accepted')->count();
    }

    /** @return Collection<int|string, int> */
    public function acceptedCountsByDate(): Collection
    {
        /** @var Collection<int|string, int> $result */
        $result = DB::table('bakery_orders')->where('status', 'accepted')
            ->select('delivery_date')->selectRaw('COUNT(*) AS count')->groupBy('delivery_date')
            ->pluck('count', 'delivery_date');

        return $result;
    }
}
