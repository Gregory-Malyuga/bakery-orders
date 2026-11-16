<?php

namespace Domains\StoreProductAvailability\Infrastructure\Repositories;

use Domains\StoreProductAvailability\Application\Repositories\AvailabilityCatalogReadRepositoryInterface;
use Illuminate\Support\Facades\DB;

final class AvailabilityCatalogReadRepository implements AvailabilityCatalogReadRepositoryInterface
{
    /** @return array<int, bool> */
    public function unavailableProducts(int $storeId): array
    {
        /** @var array<int, bool> $result */
        $result = DB::table('bakery_store_product_exclusions')->where('bakery_id', $storeId)
            ->pluck('product_id')->mapWithKeys(fn ($id): array => is_numeric($id) ? [(int) $id => true] : [])->all();

        return $result;
    }

    /** @return array<int, bool> */
    public function unavailableStores(int $productId): array
    {
        /** @var array<int, bool> $result */
        $result = DB::table('bakery_store_product_exclusions')->where('product_id', $productId)
            ->pluck('bakery_id')->mapWithKeys(fn ($id): array => is_numeric($id) ? [(int) $id => true] : [])->all();

        return $result;
    }
}
