<?php

namespace Domains\StoreProductAvailability\Infrastructure\Repositories;

use Domains\StoreProductAvailability\Application\Repositories\StoreProductAvailabilityReadRepositoryInterface;
use Illuminate\Support\Facades\DB;

final class StoreProductAvailabilityReadRepository implements StoreProductAvailabilityReadRepositoryInterface
{
    /** @return list<int> */
    public function excludedProductIds(int $storeId): array
    {
        $ids = [];
        foreach (
            DB::table('bakery_store_product_exclusions')
                ->where('bakery_id', $storeId)
                ->pluck('product_id') as $id
        ) {
            if (is_numeric($id)) {
                $ids[] = (int) $id;
            }
        }

        return $ids;
    }
}
