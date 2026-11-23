<?php

namespace Domains\Price\Application\Repositories;

interface PriceOperationsRepositoryInterface
{
    /** @param list<int> $storeIds */
    public function apply(
        int $productId,
        array $storeIds,
        int $delta,
        string $direction,
        string $key,
        int $userId,
    ): void;

    public function single(int $productId, int $storeId, int $value, int $userId): void;

    public function reverse(int $operationId, string $reason, int $userId): void;

    public function belongsToProduct(int $operationId, int $productId): bool;
}
