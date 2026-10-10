<?php

namespace Domains\Bakery\Application\Repositories;

use Domains\Bakery\Domain\Models\Bakery;
use Domains\Bakery\Domain\Models\Order;
use Domains\Bakery\Domain\Models\Product;
use Illuminate\Database\Eloquent\Collection;

interface OrderingRepositoryInterface
{
    public function store(int $id): ?Bakery;

    /** @return Collection<int, Product> */
    public function products(): Collection;

    /** @return array<int, int> */
    public function prices(int $storeId): array;

    /** @return Collection<int, Bakery> */
    public function stores(): Collection;

    /** @return Collection<int, Order> */
    public function orders(string $date): Collection;

    /** @return Collection<int, Order> */
    public function history(int $storeId): Collection;

    public function activeOrder(int $storeId, string $date): ?Order;

    public function order(int $id): ?Order;

    public function request(int $storeId, string $key): ?Order;

    /** @param callable(): (Order|array<string, mixed>) $operation
     * @return Order|array<string, mixed>
     */
    public function lockedStore(int $storeId, callable $operation): Order|array;

    /** @param list<array{product_id:int,product_name:string,quantity:int,unit_kopecks:int,total_kopecks:int}> $items */
    public function createOrder(Bakery $store, string $date, string $key, array $items, int $total, ?string $comment = null): Order;

    public function cancel(Order $order): void;
}
