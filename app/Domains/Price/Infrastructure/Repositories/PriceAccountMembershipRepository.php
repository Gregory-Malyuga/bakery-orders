<?php

namespace Domains\Price\Infrastructure\Repositories;

use Domains\Price\Application\Repositories\PriceAccountMembershipRepositoryInterface;
use Domains\Price\Domain\Models\Price;

final class PriceAccountMembershipRepository implements PriceAccountMembershipRepositoryInterface
{
    public function copySharedPricesToStores(int $oldPrimaryId, array $storeIds): void
    {
        $sharedPrices = Price::query()->where('bakery_id', $oldPrimaryId)->get();

        foreach ($storeIds as $storeId) {
            if ($storeId === $oldPrimaryId) {
                continue;
            }

            Price::query()->where('bakery_id', $storeId)->delete();
            foreach ($sharedPrices as $price) {
                Price::query()->create([
                    'bakery_id' => $storeId,
                    'product_id' => $price->product_id,
                    'kopecks' => $price->kopecks,
                ]);
            }
        }
    }
}
