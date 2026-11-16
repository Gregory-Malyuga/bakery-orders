<?php

namespace Domains\Price\Application\Repositories;

interface PriceAccountMembershipRepositoryInterface
{
    /** @param list<int> $storeIds */
    public function copySharedPricesToStores(int $oldPrimaryId, array $storeIds): void;
}
