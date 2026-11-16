<?php

namespace Domains\OrderAdjustment\Infrastructure\Repositories;

use Domains\Bakery\Domain\Models\Order;
use Domains\Bakery\Domain\Models\OrderItem;
use Domains\OrderAdjustment\Application\Repositories\OrderAdjustmentRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

final class OrderAdjustmentRepository implements OrderAdjustmentRepositoryInterface
{
    public function transaction(callable $operation): void
    {
        DB::transaction(static function () use ($operation): void {
            $operation();
        });
    }

    public function lockedOrder(int $id): Order
    {
        return Order::query()->whereKey($id)->lockForUpdate()->firstOrFail();
    }

    /** @return Collection<int, OrderItem> */
    public function items(int $orderId): Collection
    {
        return OrderItem::query()->where('order_id', $orderId)->get();
    }

    /**
     * @param list<array{product_id:int,product_name:string,quantity:int,unit_kopecks:int,total_kopecks:int}> $before
     * @param list<array{product_id:int,product_name:string,quantity:int,unit_kopecks:int,total_kopecks:int}> $after
     */
    public function save(int $orderId, int $adminId, array $before, array $after, int $total, ?string $adminNote, string $reason): void
    {
        OrderItem::query()->where('order_id', $orderId)->delete();
        foreach ($after as $row) {
            OrderItem::query()->create(['order_id' => $orderId] + $row);
        }
        $order = Order::query()->findOrFail($orderId);
        $previousTotal = (int) $order->total_kopecks;
        $order->update(['total_kopecks' => $total, 'admin_note' => $adminNote]);
        DB::table('bakery_order_adjustments')->insert([
            'order_id' => $orderId,
            'admin_user_id' => $adminId,
            'before_items' => json_encode($before, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'after_items' => json_encode($after, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'before_total_kopecks' => $previousTotal,
            'after_total_kopecks' => $total,
            'reason' => $reason,
            'created_at' => now(),
        ]);
    }
}
