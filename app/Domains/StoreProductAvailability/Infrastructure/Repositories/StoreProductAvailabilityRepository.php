<?php

namespace Domains\StoreProductAvailability\Infrastructure\Repositories;

use Domains\StoreProductAvailability\Application\Repositories\StoreProductAvailabilityRepositoryInterface;
use Illuminate\Support\Facades\DB;

final class StoreProductAvailabilityRepository implements StoreProductAvailabilityRepositoryInterface
{
    public function include(int $storeId, int $productId): void
    {
        DB::table('bakery_store_product_exclusions')
            ->where(['bakery_id' => $storeId, 'product_id' => $productId])->delete();
    }

    public function exclude(int $storeId, int $productId): void
    {
        DB::table('bakery_store_product_exclusions')
            ->updateOrInsert(['bakery_id' => $storeId, 'product_id' => $productId]);
    }
}
