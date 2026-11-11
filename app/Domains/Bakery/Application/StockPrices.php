<?php

namespace Domains\Bakery\Application;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class StockPrices
{
    public function ensureThrough(string $date): void
    {
        $today = now('Europe/Moscow')->toDateString();
        $end = min($date, $today);
        /** @var Collection<int, object{stock_item_id:int,effective_date:string,id:int,price_kopecks:int,measure_milli:int,measure_unit:string}> $changes */
        $changes = DB::table('bakery_stock_price_changes')->where('effective_date', '<=', $end)
            ->orderBy('stock_item_id')->orderBy('effective_date')->orderBy('id')->get();
        foreach ($changes->groupBy('stock_item_id') as $itemChanges) {
            $first = $itemChanges->first();
            if ($first === null) {
                continue;
            }
            $day = CarbonImmutable::parse($first->effective_date, 'Europe/Moscow');
            $last = CarbonImmutable::parse($end, 'Europe/Moscow');
            $selected = null;
            while ($day->lessThanOrEqualTo($last)) {
                $dayText = $day->toDateString();
                foreach ($itemChanges as $change) {
                    if ($change->effective_date > $dayText) {
                        break;
                    }
                    $selected = $change;
                }
                if ($selected !== null) {
                    DB::table('bakery_stock_daily_prices')->insertOrIgnore([
                        'stock_item_id' => $selected->stock_item_id,
                        'price_date' => $dayText,
                        'price_kopecks' => $selected->price_kopecks,
                        'measure_milli' => $selected->measure_milli,
                        'measure_unit' => $selected->measure_unit,
                        'source_change_id' => $selected->id,
                    ]);
                }
                $day = $day->addDay();
            }
        }
    }

    /** @return Collection<int|string, \stdClass> */
    public function forDate(string $date): Collection
    {
        $this->ensureThrough($date);

        return DB::table('bakery_stock_daily_prices')->where('price_date', $date)->get()->keyBy('stock_item_id');
    }

    public static function value(int $balanceMilli, int $priceKopecks, int $measureMilli, string $stockUnit, string $measureUnit): int
    {
        $stockFactor = in_array($stockUnit, ['kg', 'l'], true) ? 1000 : 1;

        return (int) round($balanceMilli * $stockFactor * $priceKopecks / $measureMilli);
    }

    /** @return list<array{date:string,item:string,unit:string,balance_milli:int,price_kopecks:int|null,measure_milli:int|null,measure_unit:string|null,value_kopecks:int|null}> */
    public function dailyRows(string $from, string $to): array
    {
        $this->ensureThrough($to);
        /** @var Collection<int, object{id:int,name:string,unit:string}> $items */
        $items = DB::table('bakery_stock_items')->orderBy('kind')->orderBy('name')->get();
        /** @var Collection<int|string, Collection<int, object{movement_date:string,quantity_milli:int}>> $movements */
        $movements = DB::table('bakery_stock_movements')->where('movement_date', '<=', $to)
            ->select('stock_item_id', 'movement_date')->selectRaw('SUM(quantity_milli) AS quantity_milli')
            ->groupBy('stock_item_id', 'movement_date')->orderBy('movement_date')->get()->groupBy('stock_item_id');
        /** @var Collection<int, object{stock_item_id:int,price_date:string,price_kopecks:int,measure_milli:int,measure_unit:string}> $priceRecords */
        $priceRecords = DB::table('bakery_stock_daily_prices')->whereBetween('price_date', [$from, $to])->get();
        $prices = $priceRecords->keyBy(fn (object $price): string => $price->stock_item_id.'|'.$price->price_date);
        $rows = [];
        $day = CarbonImmutable::parse($from, 'Europe/Moscow');
        $last = CarbonImmutable::parse($to, 'Europe/Moscow');
        while ($day->lessThanOrEqualTo($last)) {
            $date = $day->toDateString();
            foreach ($items as $item) {
                $balance = 0;
                foreach ($movements[$item->id] ?? [] as $movement) {
                    if ($movement->movement_date > $date) {
                        break;
                    }
                    $balance += (int) $movement->quantity_milli;
                }
                $price = $prices[$item->id.'|'.$date] ?? null;
                $rows[] = [
                    'date' => $date, 'item' => $item->name, 'unit' => $item->unit,
                    'balance_milli' => $balance, 'price_kopecks' => $price === null ? null : (int) $price->price_kopecks,
                    'measure_milli' => $price === null ? null : (int) $price->measure_milli,
                    'measure_unit' => $price?->measure_unit,
                    'value_kopecks' => $price === null ? null : self::value($balance, (int) $price->price_kopecks, (int) $price->measure_milli, $item->unit, $price->measure_unit),
                ];
            }
            $day = $day->addDay();
        }

        return $rows;
    }
}
