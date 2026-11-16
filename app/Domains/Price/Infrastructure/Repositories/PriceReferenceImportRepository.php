<?php

namespace Domains\Price\Infrastructure\Repositories;

use Domains\Price\Application\Repositories\PriceReferenceImportRepositoryInterface;
use Domains\Price\Domain\Models\Price;

final class PriceReferenceImportRepository implements PriceReferenceImportRepositoryInterface
{
    public function setOrDelete(int $storeId, int $productId, ?int $kopecks): void
    {
        $identity = ['bakery_id' => $storeId, 'product_id' => $productId];
        if ($kopecks === null) {
            Price::query()->where($identity)->delete();
        } else {
            Price::query()->updateOrCreate($identity, ['kopecks' => $kopecks]);
        }
    }
}
