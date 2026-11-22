<?php

namespace Domains\Order\Application\Repositories;

use Domains\Order\Domain\Models\Order;
use Illuminate\Database\Eloquent\Collection;

interface OrderAnalyticsReadRepositoryInterface
{
    /** @return Collection<int, Order> */
    public function acceptedOrdersBetween(string $from, string $to, ?int $storeId): Collection;
}
