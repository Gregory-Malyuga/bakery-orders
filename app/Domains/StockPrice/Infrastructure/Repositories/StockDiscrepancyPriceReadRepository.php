<?php

namespace Domains\StockPrice\Infrastructure\Repositories;

use Domains\StockPrice\Application\Repositories\StockDiscrepancyPriceReadRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class StockDiscrepancyPriceReadRepository implements StockDiscrepancyPriceReadRepositoryInterface
{
    /**
     * @param Collection<int, \stdClass> $discrepancies
     * @return Collection<string, object{stock_item_id:int,price_date:string}>
     */
    public function forDiscrepancies(Collection $discrepancies): Collection
    {
        /** @var Collection<int, object{stock_item_id:int,price_date:string}> $discrepancyPriceRecords */
        $discrepancyPriceRecords = DB::table('bakery_stock_daily_prices')
            ->whereIn('price_date', $discrepancies->pluck('movement_date')->unique())->get();
        $discrepancyPrices = $discrepancyPriceRecords->keyBy(fn (object $price): string => $price->stock_item_id.'|'.$price->price_date);
        return $discrepancyPrices;
    }
}
