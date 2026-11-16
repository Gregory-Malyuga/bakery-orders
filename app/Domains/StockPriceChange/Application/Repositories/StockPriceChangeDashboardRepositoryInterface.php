<?php

namespace Domains\StockPriceChange\Application\Repositories;

use Illuminate\Support\Collection;

interface StockPriceChangeDashboardRepositoryInterface
{
    /** @return Collection<int|string, \stdClass> */
    public function currentPrices(string $date): Collection;

    /** @return Collection<int, \stdClass> */
    public function history(): Collection;
}
