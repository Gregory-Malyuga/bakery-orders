<?php

namespace Domains\Price\Infrastructure\Repositories;

use Domains\Bakery\Domain\Models\Bakery;
use Domains\Bakery\Domain\Models\Price;
use Domains\Price\Application\Repositories\PriceOrderReadRepositoryInterface;
use Domains\Store\Application\Repositories\StoreOrderReadRepositoryInterface;
use Domains\StoreProductAvailability\Application\Repositories\StoreProductAvailabilityReadRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final class PriceOrderReadRepository implements PriceOrderReadRepositoryInterface
{
    public function __construct(
        private readonly StoreOrderReadRepositoryInterface $stores,
        private readonly StoreProductAvailabilityReadRepositoryInterface $availability,
    ) {}

    /** @return array<int, int> */
    public function forStore(int $storeId, bool $includeUnavailable = false, bool $applyDiscount = true): array
    {
        $store = $this->stores->find($storeId);
        if ($store === null) {
            throw (new ModelNotFoundException)->setModel(Bakery::class, [$storeId]);
        }

        $priceSourceId = (int) ($store->price_source_id ?: $storeId);
        $query = Price::query()->where('bakery_id', $priceSourceId);
        if (! $includeUnavailable) {
            $excluded = $this->availability->excludedProductIds($storeId);
            if ($excluded !== []) {
                $query->whereNotIn('product_id', $excluded);
            }
        }

        $prices = [];
        foreach ($query->get() as $price) {
            $prices[(int) $price->product_id] = (int) $price->kopecks;
        }

        $discount = $applyDiscount && $store->discount_enabled ? (int) $store->discount_percent : 0;
        if ($discount > 0) {
            foreach ($prices as $productId => $kopecks) {
                $prices[$productId] = intdiv($kopecks * (100 - $discount) + 50, 100);
            }
        }

        return $prices;
    }
}
