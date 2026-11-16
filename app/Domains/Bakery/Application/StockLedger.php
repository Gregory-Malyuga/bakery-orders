<?php

namespace Domains\Bakery\Application;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockLedger
{
    public function syncCatalog(): void
    {
        foreach (DB::table('bakery_ingredients')->get(['id', 'name', 'unit']) as $ingredient) {
            DB::table('bakery_stock_items')->insertOrIgnore([
                'kind' => 'ingredient', 'ingredient_id' => $ingredient->id, 'name' => $ingredient->name,
                'unit' => $ingredient->unit, 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('bakery_stock_items')->where('ingredient_id', $ingredient->id)
                ->where(function (Builder $query) use ($ingredient): void {
                    $query->where('name', '!=', $ingredient->name)->orWhere('unit', '!=', $ingredient->unit);
                })->update(['name' => $ingredient->name, 'unit' => $ingredient->unit, 'updated_at' => now()]);
        }
        foreach (DB::table('bakery_products')->get(['id', 'name']) as $product) {
            DB::table('bakery_stock_items')->insertOrIgnore([
                'kind' => 'product', 'product_id' => $product->id, 'name' => $product->name,
                'unit' => 'pcs', 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('bakery_stock_items')->where('product_id', $product->id)->where('name', '!=', $product->name)
                ->update(['name' => $product->name, 'updated_at' => now()]);
        }
    }

    public function quantity(string $value): int
    {
        $value = str_replace(',', '.', trim($value));
        if (! preg_match('/^\d{1,9}(?:\.\d{1,3})?$/', $value)) {
            throw ValidationException::withMessages(['quantity' => 'Укажите количество до трёх знаков после запятой.']);
        }
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');

        return (int) $whole * 1000 + (int) str_pad($fraction, 3, '0');
    }

    public function record(int $itemId, string $kind, int $quantity, string $reason, int $actorId): void
    {
        if (! in_array($kind, ['opening', 'receipt', 'consumption', 'writeoff', 'return'], true) || $quantity <= 0) {
            throw ValidationException::withMessages(['quantity' => 'Укажите положительное количество.']);
        }
        $delta = in_array($kind, ['consumption', 'writeoff'], true) ? -$quantity : $quantity;
        $this->append($itemId, $kind, $delta, $reason, $actorId);
    }

    public function inventory(int $itemId, int $counted, string $reason, int $actorId): void
    {
        if ($counted < 0) {
            throw ValidationException::withMessages(['quantity' => 'Остаток не может быть отрицательным.']);
        }
        $this->append($itemId, 'inventory', null, $reason, $actorId, $counted);
    }

    public function reverse(int $movementId, string $reason, int $actorId): void
    {
        DB::transaction(function () use ($movementId, $reason, $actorId): void {
            /** @var object{stock_item_id:int,kind:string,quantity_milli:int}|null $original */
            $original = DB::table('bakery_stock_movements')->find($movementId);
            if ($original === null || $original->kind === 'reversal') {
                throw ValidationException::withMessages(['movement' => 'Движение не найдено или не может быть отменено.']);
            }
            $this->append($original->stock_item_id, 'reversal', -$original->quantity_milli, $reason, $actorId, null, $movementId);
        });
    }

    private function append(int $itemId, string $kind, ?int $delta, string $reason, int $actorId, ?int $counted = null, ?int $reversesId = null): void
    {
        DB::transaction(static function () use ($itemId, $kind, $delta, $reason, $actorId, $counted, $reversesId): void {
            $item = DB::table('bakery_stock_items')->where('id', $itemId)->lockForUpdate()->first();
            if ($item === null) {
                throw ValidationException::withMessages(['item' => 'Позиция склада не найдена.']);
            }
            if ($reversesId !== null && DB::table('bakery_stock_movements')->where('reverses_id', $reversesId)->exists()) {
                throw ValidationException::withMessages(['movement' => 'Движение уже отменено.']);
            }
            $balance = (int) DB::table('bakery_stock_movements')->where('stock_item_id', $itemId)->sum('quantity_milli');
            $actualDelta = $counted === null ? (int) $delta : $counted - $balance;
            if ($balance + $actualDelta < 0) {
                throw ValidationException::withMessages(['quantity' => 'Недостаточно остатка. Отрицательный остаток запрещён.']);
            }
            DB::table('bakery_stock_movements')->insert([
                'stock_item_id' => $itemId, 'movement_date' => now('Europe/Moscow')->toDateString(),
                'kind' => $kind, 'quantity_milli' => $actualDelta, 'balance_milli' => $balance + $actualDelta,
                'counted_milli' => $counted, 'actor_id' => $actorId, 'reason' => $reason,
                'reverses_id' => $reversesId, 'created_at' => now(), 'updated_at' => now(),
            ]);
        });
    }
}
