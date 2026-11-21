<?php

namespace Domains\OrderReturn\Infrastructure\Repositories;

use Domains\OrderReturn\Application\Repositories\OrderReturnReadRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class OrderReturnReadRepository implements OrderReturnReadRepositoryInterface
{
    /** @param list<int> $ids
     * @return Collection<int, \stdClass>
     */
    public function activeForOrders(array $ids): Collection
    {
        return DB::table('bakery_returns')->whereIn('order_id', $ids)
            ->where('status', 'active')->orderBy('id')->get();
    }

    /** @return Collection<int, \stdClass> */
    public function historyForOrder(int $orderId): Collection
    {
        return DB::table('bakery_returns')
            ->leftJoin('users as cancelling_users', 'bakery_returns.cancelled_by_user_id', '=', 'cancelling_users.id')
            ->where('bakery_returns.order_id', $orderId)->orderByDesc('bakery_returns.id')
            ->select('bakery_returns.*', 'cancelling_users.name as cancelled_by_name')->get();
    }
}
