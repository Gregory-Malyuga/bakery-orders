<?php

namespace Domains\Store\Application\Repositories;

use Illuminate\Support\Collection;

interface StorePriceSourceRepositoryInterface
{
    /** @return Collection<int, \stdClass> */
    public function lockAllPriceSources(): Collection;

    public function lockStore(int $storeId): object;

    /** @return Collection<int, \stdClass> */
    public function linkedStoreIds(int $sourceId): Collection;
}
