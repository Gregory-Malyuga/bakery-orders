<?php

namespace Domains\StoreProductAvailability\Application\Repositories;

interface AvailabilityCatalogReadRepositoryInterface
{
    /** @return array<int, bool> */
    public function unavailableProducts(int $storeId): array;

    /** @return array<int, bool> */
    public function unavailableStores(int $productId): array;
}
