<?php

namespace Domains\StockPrice\Application\Repositories;

use Illuminate\Support\Collection;

interface StockDiscrepancyPriceReadRepositoryInterface
{
    /** @param Collection<int, \stdClass> $discrepancies
     * @return Collection<string, object{stock_item_id:int,price_date:string}>
     */
    public function forDiscrepancies(Collection $discrepancies): Collection;
}
