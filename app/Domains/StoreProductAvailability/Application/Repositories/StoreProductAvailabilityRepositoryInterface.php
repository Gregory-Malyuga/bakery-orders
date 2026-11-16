<?php

namespace Domains\StoreProductAvailability\Application\Repositories;

interface StoreProductAvailabilityRepositoryInterface
{
    public function include(int $storeId, int $productId): void;

    public function exclude(int $storeId, int $productId): void;
}
