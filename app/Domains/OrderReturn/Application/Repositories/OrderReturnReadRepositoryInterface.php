<?php

namespace Domains\OrderReturn\Application\Repositories;

use Illuminate\Support\Collection;

interface OrderReturnReadRepositoryInterface
{
    /** @param list<int> $ids
     * @return Collection<int, \stdClass>
     */
    public function activeForOrders(array $ids): Collection;

    /** @return Collection<int, \stdClass> */
    public function historyForOrder(int $orderId): Collection;
}
