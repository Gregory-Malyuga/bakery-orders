<?php

namespace Domains\Order\Infrastructure\Repositories;

use Domains\Order\Application\Repositories\OrderRepositoryInterface;
use Domains\Bakery\Domain\Models\Bakery;
use Domains\Bakery\Domain\Models\Order;
use Domains\Bakery\Domain\Models\OrderItem;
use Illuminate\Database\Eloquent\Collection;
use Domains\Store\Application\Repositories\StoreOrderReadRepositoryInterface;
use Domains\Product\Application\Repositories\ProductOrderReadRepositoryInterface;
use Domains\Price\Application\Repositories\PriceOrderReadRepositoryInterface;
use Domains\StoreProductMinimum\Application\Repositories\StoreProductMinimumReadRepositoryInterface;
use Illuminate\Support\Facades\DB;

class OrderRepository implements OrderRepositoryInterface
{
    public function __construct(
        private readonly StoreOrderReadRepositoryInterface $storesRead,
        private readonly ProductOrderReadRepositoryInterface $productsRead,
        private readonly PriceOrderReadRepositoryInterface $pricesRead,
        private readonly StoreProductMinimumReadRepositoryInterface $minimumsRead,
    ) {}

    public function store(int $id): ?Bakery
    {
        return $this->storesRead->find($id);
    }

    public function products(): Collection
    {
        return $this->productsRead->all();
    }

    public function prices(int $storeId, bool $includeUnavailable = false, bool $applyDiscount = true): array
    {
        return $this->pricesRead->forStore($storeId, $includeUnavailable, $applyDiscount);
    }

    public function minimums(int $storeId): array
    {
        return $this->minimumsRead->minimumsForStore($storeId);
    }

    public function stores(): Collection
    {
        return $this->storesRead->all();
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
            $this->storesRead->lockForUpdate($storeId);

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
