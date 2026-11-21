<?php

namespace Domains\StockPriceChange\Infrastructure\Repositories;

use Domains\StockPriceChange\Application\Repositories\StockPriceChangeDashboardRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class StockPriceChangeDashboardRepository implements StockPriceChangeDashboardRepositoryInterface
{
    /** @return Collection<int|string, \stdClass> */
    public function currentPrices(string $date): Collection
    {
        $tomorrowPrices = DB::table('bakery_stock_price_changes')->where('effective_date', '<=', $date)
            ->orderBy('stock_item_id')->orderByDesc('effective_date')->orderByDesc('id')->get()->unique('stock_item_id')->keyBy('stock_item_id');

        return $tomorrowPrices;
    }

    /** @return Collection<int, \stdClass> */
    public function history(): Collection
    {
        $priceHistory = DB::table('bakery_stock_price_changes as price')
            ->join('bakery_stock_items as item', 'item.id', '=', 'price.stock_item_id')
            ->join('users as actor', 'actor.id', '=', 'price.actor_id')
            ->select('price.*', 'item.name as item_name', 'actor.name as actor_name')
            ->orderByDesc('price.id')->limit(50)->get();

        return $priceHistory;
    }
}
