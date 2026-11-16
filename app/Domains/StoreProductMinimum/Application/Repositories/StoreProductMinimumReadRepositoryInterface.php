<?php

namespace Domains\StoreProductMinimum\Application\Repositories;

interface StoreProductMinimumReadRepositoryInterface
{
    /** @return array<int, int> */
    public function minimumsForStore(int $storeId): array;
}
