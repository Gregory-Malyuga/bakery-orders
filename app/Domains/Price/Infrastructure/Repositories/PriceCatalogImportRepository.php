<?php

namespace Domains\Price\Infrastructure\Repositories;

use Domains\Price\Application\Repositories\PriceCatalogImportRepositoryInterface;
use Domains\Price\Domain\Models\Price;

final class PriceCatalogImportRepository implements PriceCatalogImportRepositoryInterface
{
    public function put(int $storeId, int $productId, int $kopecks): void
    {
        Price::query()->updateOrCreate(
            ['bakery_id' => $storeId, 'product_id' => $productId], ['kopecks' => $kopecks],
        );
    }
}
