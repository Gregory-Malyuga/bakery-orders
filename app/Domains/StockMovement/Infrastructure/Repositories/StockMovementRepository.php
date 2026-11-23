<?php

namespace Domains\StockMovement\Infrastructure\Repositories;

use Domains\StockMovement\Application\Repositories\StockMovementRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class StockMovementRepository implements StockMovementRepositoryInterface
{
    public function reverse(int $movementId, string $reason, int $actorId): void
    {
        DB::transaction(function () use ($movementId, $reason, $actorId): void {
            /** @var object{stock_item_id:int,kind:string,quantity_milli:int}|null $original */
            $original = DB::table('bakery_stock_movements')->find($movementId);
            if ($original === null || $original->kind === 'reversal') {
                throw ValidationException::withMessages([
                    'movement' => 'Движение не найдено или не может быть отменено.',
                ]);
            }
            $this->append(
                $original->stock_item_id,
                'reversal',
                -$original->quantity_milli,
                $reason,
                $actorId,
                null,
                $movementId,
            );
        });
    }

    public function append(
        int $itemId,
        string $kind,
        ?int $delta,
        string $reason,
        int $actorId,
        ?int $counted = null,
        ?int $reversesId = null,
    ): void {
        DB::transaction(static function () use (
            $itemId,
            $kind,
            $delta,
            $reason,
            $actorId,
            $counted,
            $reversesId,
        ): void {
            $item = DB::table('bakery_stock_items')->where('id', $itemId)->lockForUpdate()->first();
            if ($item === null) {
                throw ValidationException::withMessages(['item' => 'Позиция склада не найдена.']);
            }
            if (
                $reversesId !== null &&
                DB::table('bakery_stock_movements')->where('reverses_id', $reversesId)->exists()
            ) {
                throw ValidationException::withMessages(['movement' => 'Движение уже отменено.']);
            }
            $balance = (int) DB::table('bakery_stock_movements')
                ->where('stock_item_id', $itemId)
                ->sum('quantity_milli');
            $actualDelta = $counted === null ? (int) $delta : $counted - $balance;
            if ($balance + $actualDelta < 0) {
                throw ValidationException::withMessages([
                    'quantity' => 'Недостаточно остатка. Отрицательный остаток запрещён.',
                ]);
            }
            DB::table('bakery_stock_movements')->insert([
                'stock_item_id' => $itemId,
                'movement_date' => now('Europe/Moscow')->toDateString(),
                'kind' => $kind,
                'quantity_milli' => $actualDelta,
                'balance_milli' => $balance + $actualDelta,
                'counted_milli' => $counted,
                'actor_id' => $actorId,
                'reason' => $reason,
                'reverses_id' => $reversesId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }
}
