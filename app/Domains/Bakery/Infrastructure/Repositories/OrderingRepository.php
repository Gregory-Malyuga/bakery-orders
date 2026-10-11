<?php

namespace Domains\Bakery\Infrastructure\Repositories;

use Domains\Bakery\Application\Repositories\OrderingRepositoryInterface;
use Domains\Bakery\Domain\Models\Bakery;
use Domains\Bakery\Domain\Models\Order;
use Domains\Bakery\Domain\Models\OrderItem;
use Domains\Bakery\Domain\Models\Price;
use Domains\Bakery\Domain\Models\Product;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class OrderingRepository implements OrderingRepositoryInterface
{
    public function store(int $id): ?Bakery
    {
        return Bakery::query()->find($id);
    }

    public function products(): Collection
    {
        return Product::query()->orderBy('sort_order')->orderBy('id')->get();
    }

    public function prices(int $storeId): array
    {
        $store = Bakery::query()->findOrFail($storeId);
        $priceStoreId = (int) ($store->price_source_id ?: $storeId);
        $prices = [];
        foreach (Price::query()->where('bakery_id', $priceStoreId)->get() as $price) {
            $prices[(int) $price->product_id] = (int) $price->kopecks;
        }

        return $prices;
    }

    public function minimums(int $storeId): array
    {
        return DB::table('bakery_store_product_minimums')->where('bakery_id', $storeId)->pluck('minimum_quantity', 'product_id')->mapWithKeys(fn ($quantity, $productId): array => [(int) $productId => (int) $quantity])->all();
    }

    public function stores(): Collection
    {
        return Bakery::query()->orderBy('name')->get();
    }

    public function orders(string $date): Collection
    {
        return Order::query()->with('items')->whereDate('delivery_date', $date)->where('status', 'accepted')->orderBy('bakery_id')->get();
    }

    public function history(int $storeId): Collection
    {
        return Order::query()->with('items')->where('bakery_id', $storeId)->latest('id')->limit(30)->get();
    }

    public function activeOrder(int $storeId, string $date): ?Order
    {
        return Order::query()->with('items')->where('bakery_id', $storeId)->whereDate('delivery_date', $date)->where('status', 'accepted')->first();
    }

    public function order(int $id): ?Order
    {
        return Order::query()->with('items')->find($id);
    }

    public function request(int $storeId, string $key): ?Order
    {
        return Order::query()->with('items')->where('bakery_id', $storeId)->where('request_key', $key)->first();
    }

    public function lockedStore(int $storeId, callable $operation): Order|array
    {
        return DB::transaction(function () use ($storeId, $operation): Order|array {
            Bakery::query()->whereKey($storeId)->lockForUpdate()->firstOrFail();

            return $operation();
        });
    }

    public function createOrder(Bakery $store, string $date, string $key, array $items, int $total, ?string $comment = null): Order
    {
        $order = Order::query()->create([
            'bakery_id' => $store->id,
            'delivery_date' => $date,
            'status' => 'accepted',
            'active_slot' => 1,
            'request_key' => $key,
            'store_name' => $store->name,
            'store_address' => $store->address,
            'total_kopecks' => $total,
            'comment' => $comment,
        ]);
        foreach ($items as $item) {
            OrderItem::query()->create(['order_id' => $order->id] + $item);
        }

        return $order->load('items');
    }

    public function cancel(Order $order): void
    {
        $order->update(['status' => 'cancelled', 'active_slot' => null, 'cancelled_at' => now('Europe/Moscow')]);
    }
}
