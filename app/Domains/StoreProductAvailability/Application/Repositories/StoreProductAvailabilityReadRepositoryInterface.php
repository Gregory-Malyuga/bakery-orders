<?php

namespace Domains\StoreProductAvailability\Application\Repositories;

interface StoreProductAvailabilityReadRepositoryInterface
{
    /** @return list<int> */
    public function excludedProductIds(int $storeId): array;
}
