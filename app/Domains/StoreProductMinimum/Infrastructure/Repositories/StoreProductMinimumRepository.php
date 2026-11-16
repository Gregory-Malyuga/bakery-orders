<?php

namespace Domains\StoreProductMinimum\Infrastructure\Repositories;

use Domains\StoreProductMinimum\Application\Repositories\StoreProductMinimumRepositoryInterface;
use Illuminate\Support\Facades\DB;

final class StoreProductMinimumRepository implements StoreProductMinimumRepositoryInterface
{
    public function delete(int $storeId, int $productId): void
    {
        DB::table('bakery_store_product_minimums')->where(['bakery_id' => $storeId, 'product_id' => $productId])->delete();
    }

    public function save(int $storeId, int $productId, int $minimum): void
    {
        DB::table('bakery_store_product_minimums')->updateOrInsert(
            ['bakery_id' => $storeId, 'product_id' => $productId],
            ['minimum_quantity' => $minimum],
        );
    }
    public function normalizeForProduct(int $productId, int $minimum): void
    {
        DB::table('bakery_store_product_minimums')->where('product_id', $productId)
            ->where('minimum_quantity', '<', $minimum)
            ->update(['minimum_quantity' => $minimum]);
    }
}
