<?php

namespace Domains\StockPriceChange\Infrastructure\Repositories;

use Domains\StockPriceChange\Application\Repositories\StockPriceChangeRepositoryInterface;
use Illuminate\Support\Facades\DB;

final class StockPriceChangeRepository implements StockPriceChangeRepositoryInterface
{
    /** @return object{price_kopecks:int,measure_milli:int,measure_unit:string}|null */
    public function latestForDate(int $itemId, string $date): ?object
    {
        /** @var object{price_kopecks:int,measure_milli:int,measure_unit:string}|null $record */
        $record = DB::table('bakery_stock_price_changes')
            ->where('stock_item_id', $itemId)
            ->where('effective_date', '<=', $date)
            ->orderByDesc('effective_date')
            ->orderByDesc('id')
            ->first();

        return $record;
    }

    public function record(array $values): void
    {
        DB::table('bakery_stock_price_changes')->insert($values);
    }
}
