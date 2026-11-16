<?php

namespace Domains\Store\Application\Repositories;

use Illuminate\Support\Collection;

interface StoreAnalyticsReadRepositoryInterface
{
    /** @param list<int> $excludedStoreIds
     * @return list<string>
     */
    public function activeStoreNamesWithoutOrders(array $excludedStoreIds, ?int $storeId): array;

    /** @return Collection<int, \stdClass> */
    public function storeOptions(): Collection;
}
