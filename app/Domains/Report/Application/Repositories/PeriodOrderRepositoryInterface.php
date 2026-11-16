<?php

namespace Domains\Report\Application\Repositories;

use Domains\Bakery\Domain\Models\Order;
use Illuminate\Database\Eloquent\Collection;

interface PeriodOrderRepositoryInterface
{
    /** @return Collection<int, Order> */
    public function forPeriod(string $from, string $to): Collection;
}
