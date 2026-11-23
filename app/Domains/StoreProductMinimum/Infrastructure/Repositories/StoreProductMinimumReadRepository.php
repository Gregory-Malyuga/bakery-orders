<?php

namespace Domains\StoreProductMinimum\Infrastructure\Repositories;

use Domains\StoreProductMinimum\Application\Repositories\StoreProductMinimumReadRepositoryInterface;
use Illuminate\Support\Facades\DB;

final class StoreProductMinimumReadRepository implements StoreProductMinimumReadRepositoryInterface
{
    /** @return array<int, int> */
    public function minimumsForStore(int $storeId): array
    {
        /** @var array<int, int> $minimums */
        $minimums = DB::table('bakery_store_product_minimums')
            ->where('bakery_id', $storeId)
            ->pluck('minimum_quantity', 'product_id')
            ->mapWithKeys(
                fn ($quantity, $id): array => is_numeric($quantity)
                    ? [(int) $id => (int) $quantity]
                    : [],
            )
            ->all();

        return $minimums;
    }
}
