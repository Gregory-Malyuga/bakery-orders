<?php

namespace Domains\OrderAdjustment\Application\Repositories;

use Domains\Order\Domain\Models\Order;
use Domains\OrderItem\Domain\Models\OrderItem;
use Illuminate\Database\Eloquent\Collection;

interface OrderAdjustmentRepositoryInterface
{
    /** @param callable():void $operation */
    public function transaction(callable $operation): void;

    public function lockedOrder(int $id): Order;

    /** @return Collection<int, OrderItem> */
    public function items(int $orderId): Collection;

    /**
     * @param  list<array{product_id:int,product_name:string,quantity:int,unit_kopecks:int,total_kopecks:int}>  $before
     * @param  list<array{product_id:int,product_name:string,quantity:int,unit_kopecks:int,total_kopecks:int}>  $after
     */
    public function save(int $orderId, int $adminId, array $before, array $after, int $total, ?string $adminNote, string $reason): void;
}
