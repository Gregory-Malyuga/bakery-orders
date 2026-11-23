<?php

namespace Domains\Store\Infrastructure\Repositories;

use Domains\Store\Application\Repositories\StoreAccountPriceSourceRepositoryInterface;
use Illuminate\Support\Facades\DB;

final class StoreAccountPriceSourceRepository implements StoreAccountPriceSourceRepositoryInterface
{
    public function detachFromSharedPriceSource(array $storeIds, int $sourceId): void
    {
        DB::table('bakeries')
            ->whereIn('id', $storeIds)
            ->where('price_source_id', $sourceId)
            ->update(['price_source_id' => null]);
    }

    public function sharePriceSource(array $storeIds): void
    {
        if (count($storeIds) < 2) {
            return;
        }

        $sourceId = $storeIds[0];
        DB::table('bakeries')
            ->where('id', $sourceId)
            ->update(['price_source_id' => null]);
        DB::table('bakeries')
            ->whereIn('id', array_slice($storeIds, 1))
            ->update(['price_source_id' => $sourceId]);
    }
}
