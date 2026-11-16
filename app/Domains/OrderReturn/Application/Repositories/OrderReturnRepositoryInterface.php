<?php

namespace Domains\OrderReturn\Application\Repositories;

use Domains\Bakery\Domain\Models\Order;
use Domains\Bakery\Domain\Models\OrderItem;

interface OrderReturnRepositoryInterface
{
    /** @param callable():void $operation */
    public function transaction(callable $operation): void;

    public function lockedOrder(int $orderId): Order;

    public function item(int $orderId, int $productId): ?OrderItem;

    /** @return object{status:string}|null */
    public function lockedReturn(int $orderId, int $returnId): ?object;

    public function record(int $orderId, int $adminId, int $productId, string $name, int $unit, int $quantity, bool $isOrderItem, ?string $reason): void;

    public function cancel(int $returnId, int $adminId, string $reason): void;
}
