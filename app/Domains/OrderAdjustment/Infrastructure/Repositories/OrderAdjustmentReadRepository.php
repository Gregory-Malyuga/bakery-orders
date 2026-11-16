<?php

namespace Domains\OrderAdjustment\Infrastructure\Repositories;

use Domains\OrderAdjustment\Application\Repositories\OrderAdjustmentReadRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class OrderAdjustmentReadRepository implements OrderAdjustmentReadRepositoryInterface
{
    /** @return Collection<int, \stdClass> */
    public function history(int $orderId): Collection
    {
        return DB::table('bakery_order_adjustments')->where('order_id', $orderId)->orderByDesc('id')->get();
    }
}
