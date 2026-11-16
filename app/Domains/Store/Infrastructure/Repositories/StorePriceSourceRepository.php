<?php

namespace Domains\Store\Infrastructure\Repositories;

use Domains\Store\Application\Repositories\StorePriceSourceRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class StorePriceSourceRepository implements StorePriceSourceRepositoryInterface
{
    /** @return Collection<int, \stdClass> */
    public function lockAllPriceSources(): Collection
    {
        return DB::table('bakeries')->orderBy('id')->lockForUpdate()->get(['id', 'name', 'price_source_id']);
    }

    public function lockStore(int $storeId): object
    {
        return DB::table('bakeries')->where('id', $storeId)->lockForUpdate()->firstOrFail();
    }

    /** @return Collection<int, \stdClass> */
    public function linkedStoreIds(int $sourceId): Collection
    {
        return DB::table('bakeries')->where('id', $sourceId)->orWhere('price_source_id', $sourceId)->get(['id']);
    }
}
