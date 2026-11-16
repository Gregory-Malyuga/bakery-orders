<?php

namespace Domains\Bakery\Application;

use Domains\Bakery\Application\Repositories\OrderingRepositoryInterface;
use Domains\Bakery\Domain\Models\Order;
use Domains\Bakery\Domain\Models\OrderItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderManagementService
{
    public function __construct(private readonly OrderingRepositoryInterface $orders) {}

    /** @param array<int|string, int|string> $quantities */
    public function adjust(int $orderId, int $adminId, array $quantities, string $reason, ?string $adminNote): void
    {
        DB::transaction(function () use ($orderId, $adminId, $quantities, $reason, $adminNote): void {
            $order = Order::query()->whereKey($orderId)->lockForUpdate()->firstOrFail();
            abort_unless($order->status === 'accepted', 422, 'Отменённый заказ нельзя изменить.');
            $items = $order->items()->get()->keyBy('product_id');
            $before = $items->map(fn (OrderItem $item): array => $item->only(['product_id', 'product_name', 'quantity', 'unit_kopecks', 'total_kopecks']))->values()->all();
            $prices = $this->orders->prices((int) $order->bakery_id);
            $products = $this->orders->products()->keyBy('id');
            $after = [];
            $total = 0;
            foreach ($quantities as $rawId => $rawQuantity) {
                if (! preg_match('/^[1-9][0-9]*$/', (string) $rawId)) {
                    throw ValidationException::withMessages(['quantities' => 'Некорректный товар в заявке.']);
                }
                $id = (int) $rawId;
                $quantity = (int) $rawQuantity;
                $item = $items->get($id);
                $returned = (int) DB::table('bakery_returns')->where('order_id', $orderId)->where('product_id', $id)->sum('quantity');
                if ($quantity < $returned) {
                    throw ValidationException::withMessages(['quantities' => 'Количество не может быть меньше уже зафиксированного возврата.']);
                }
                if ($quantity === 0) {
                    continue;
                }
                $product = $products->get($id);
                if ($item === null) {
                    if ($product === null || ! $product->is_active || ! isset($prices[$id])) {
                        throw ValidationException::withMessages(['quantities' => 'Новый товар недоступен или не имеет цены для магазина.']);
                    }
                    $unit = $prices[$id];
                    $name = $product->name;
                } else {
                    $unit = $item->unit_kopecks;
                    $name = $item->product_name;
                }
                $row = ['product_id' => $id, 'product_name' => $name, 'quantity' => $quantity, 'unit_kopecks' => $unit, 'total_kopecks' => $quantity * $unit];
                $after[] = $row;
                $total += $row['total_kopecks'];
            }
            foreach ($items as $item) {
                if (! array_key_exists((int) $item->product_id, $quantities)) {
                    throw ValidationException::withMessages(['quantities' => 'Передайте количество для каждой позиции заказа.']);
                }
            }
            if ($after === []) {
                throw ValidationException::withMessages(['quantities' => 'В заказе должна остаться хотя бы одна позиция.']);
            }
            foreach ($items as $item) {
                $item->delete();
            }
            foreach ($after as $row) {
                OrderItem::query()->create(['order_id' => $orderId] + $row);
            }
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
        });
    }

    public function recordReturn(int $orderId, int $adminId, int $productId, int $quantity, ?string $reason): void
    {
        DB::transaction(function () use ($orderId, $adminId, $productId, $quantity, $reason): void {
            $order = Order::query()->whereKey($orderId)->lockForUpdate()->firstOrFail();
            abort_unless($order->status === 'accepted', 422, 'Возврат можно зафиксировать только для принятого заказа.');
            $item = $order->items()->where('product_id', $productId)->first();
            abort_unless($item !== null, 422, 'Товар отсутствует в заказе.');
            $returned = (int) DB::table('bakery_returns')->where('order_id', $orderId)->where('product_id', $productId)->sum('quantity');
            if ($returned + $quantity > $item->quantity) {
                throw ValidationException::withMessages(['quantity' => 'Возврат превышает количество в заказе.']);
            }
            DB::table('bakery_returns')->insert([
                'order_id' => $orderId,
                'product_id' => $productId,
                'admin_user_id' => $adminId,
                'quantity' => $quantity,
                'reason' => $reason,
                'created_at' => now(),
            ]);
        });
    }
}
