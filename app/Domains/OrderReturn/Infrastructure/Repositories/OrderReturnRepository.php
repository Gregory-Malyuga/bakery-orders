<?php

namespace Domains\OrderReturn\Infrastructure\Repositories;

use Domains\Bakery\Domain\Models\Order;
use Domains\Bakery\Domain\Models\OrderItem;
use Domains\OrderReturn\Application\Repositories\OrderReturnRepositoryInterface;
use Illuminate\Support\Facades\DB;

final class OrderReturnRepository implements OrderReturnRepositoryInterface
{
    public function transaction(callable $operation): void
    {
        DB::transaction(static function () use ($operation): void {
            $operation();
        });
    }

    public function lockedOrder(int $orderId): Order
    {
        return Order::query()->whereKey($orderId)->lockForUpdate()->firstOrFail();
    }

    public function item(int $orderId, int $productId): ?OrderItem
    {
        return OrderItem::query()->where('order_id', $orderId)->where('product_id', $productId)->first();
    }

    /** @return object{status:string}|null */
    public function lockedReturn(int $orderId, int $returnId): ?object
    {
        /** @var object{status:string}|null $record */
        $record = DB::table('bakery_returns')->where('order_id', $orderId)
            ->where('id', $returnId)->lockForUpdate()->first();

        return $record;
    }

    public function record(int $orderId, int $adminId, int $productId, string $name, int $unit, int $quantity, bool $isOrderItem, ?string $reason): void
    {
        DB::table('bakery_returns')->insert([
            'order_id' => $orderId,
            'product_id' => $productId,
            'product_name' => $name,
            'unit_kopecks' => $unit,
            'is_order_item' => $isOrderItem,
            'admin_user_id' => $adminId,
            'quantity' => $quantity,
            'reason' => $reason,
            'status' => 'active',
            'created_at' => now(),
        ]);
    }

    public function cancel(int $returnId, int $adminId, string $reason): void
    {
        DB::table('bakery_returns')->where('id', $returnId)->update([
            'status' => 'cancelled',
            'cancelled_by_user_id' => $adminId,
            'cancelled_at' => now(),
            'cancellation_reason' => $reason,
        ]);
    }
}
