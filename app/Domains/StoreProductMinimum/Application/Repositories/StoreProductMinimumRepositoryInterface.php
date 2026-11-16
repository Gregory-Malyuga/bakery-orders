<?php

namespace Domains\StoreProductMinimum\Application\Repositories;

interface StoreProductMinimumRepositoryInterface
{
    public function delete(int $storeId, int $productId): void;

    public function save(int $storeId, int $productId, int $minimum): void;

    public function normalizeForProduct(int $productId, int $minimum): void;
}
