<?php

namespace Domains\OrderAdjustment\Application\Repositories;

use Illuminate\Support\Collection;

interface OrderAdjustmentReadRepositoryInterface
{
    /** @return Collection<int, \stdClass> */
    public function history(int $orderId): Collection;
}
