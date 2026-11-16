<?php

namespace Domains\Store\Application\Repositories;

interface StoreAccountPriceSourceRepositoryInterface
{
    /** @param list<int> $storeIds */
    public function detachFromSharedPriceSource(array $storeIds, int $sourceId): void;

    /** @param list<int> $storeIds */
    public function sharePriceSource(array $storeIds): void;
}
