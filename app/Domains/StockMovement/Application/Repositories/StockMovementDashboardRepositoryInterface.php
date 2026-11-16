<?php

namespace Domains\StockMovement\Application\Repositories;

use Illuminate\Support\Collection;

interface StockMovementDashboardRepositoryInterface
{
    /** @return Collection<int|string, mixed> */
    public function balances(string $date): Collection;

    /** @return Collection<int, \stdClass> */
    public function movements(string $date): Collection;

    /** @return Collection<int, \stdClass> */
    public function discrepancies(string $date): Collection;
}
