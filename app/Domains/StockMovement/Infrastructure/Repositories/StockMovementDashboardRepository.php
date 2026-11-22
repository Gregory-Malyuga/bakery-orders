<?php

namespace Domains\StockMovement\Infrastructure\Repositories;

use Domains\StockMovement\Application\Repositories\StockMovementDashboardRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class StockMovementDashboardRepository implements StockMovementDashboardRepositoryInterface
{
    /** @return Collection<int|string, mixed> */
    public function balances(string $date): Collection
    {
        $balances = DB::table('bakery_stock_movements')->where('movement_date', '<=', $date)
            ->select('stock_item_id')->selectRaw('SUM(quantity_milli) AS balance')->groupBy('stock_item_id')
            ->pluck('balance', 'stock_item_id');

        return $balances;
    }

    /** @return Collection<int, \stdClass> */
    public function movements(string $date): Collection
    {
        $movements = DB::table('bakery_stock_movements as movement')
            ->join('bakery_stock_items as item', 'item.id', '=', 'movement.stock_item_id')
            ->join('users as actor', 'actor.id', '=', 'movement.actor_id')
            ->where('movement.movement_date', '<=', $date)
            ->orderByDesc('movement.movement_date')->orderByDesc('movement.id')
            ->select('movement.*', 'item.name as item_name', 'item.unit', 'actor.name as actor_name')->limit(100)->get();

        return $movements;
    }

    /** @return Collection<int, \stdClass> */
    public function discrepancies(string $date): Collection
    {
        $discrepancies = DB::table('bakery_stock_movements as movement')
            ->join('bakery_stock_items as item', 'item.id', '=', 'movement.stock_item_id')
            ->where('movement.kind', 'inventory')->where('movement.movement_date', '<=', $date)
            ->whereNotIn('movement.id', DB::table('bakery_stock_movements')->whereNotNull('reverses_id')->select('reverses_id'))
            ->orderByDesc('movement.id')->select('movement.*', 'item.name as item_name', 'item.unit')->limit(50)->get();

        return $discrepancies;
    }
}
