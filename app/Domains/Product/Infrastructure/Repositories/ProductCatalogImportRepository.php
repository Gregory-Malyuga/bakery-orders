<?php

namespace Domains\Product\Infrastructure\Repositories;

use Domains\Product\Application\Repositories\ProductCatalogImportRepositoryInterface;
use Domains\Product\Domain\Models\Product;

final class ProductCatalogImportRepository implements ProductCatalogImportRepositoryInterface
{
    public function firstOrCreate(string $name): int
    {
        return (int) Product::query()->firstOrCreate(['name' => $name], ['is_active' => true])->id;
    }
}
