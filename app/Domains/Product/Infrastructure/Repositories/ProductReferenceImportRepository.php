<?php

namespace Domains\Product\Infrastructure\Repositories;

use Domains\Bakery\Domain\Models\Product;
use Domains\Product\Application\Repositories\ProductReferenceImportRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

final class ProductReferenceImportRepository implements ProductReferenceImportRepositoryInterface
{
    /** @return Collection<int, Product> */
    public function all(): Collection
    {
        return Product::query()->get();
    }

    public function activateOrCreate(string $name, int $position, string $packagingMode, int $minimum): Product
    {
        $product = Product::query()->firstOrCreate(['name' => $name], [
            'sort_order' => $position,
            'minimum_quantity' => $minimum,
            'packaging_mode' => $packagingMode,
            'is_active' => true,
        ]);
        $product->update(['sort_order' => $position, 'is_active' => true]);

        return $product;
    }
    public function activate(int $productId, int $position): void
    {
        Product::query()->whereKey($productId)->update(['sort_order' => $position, 'is_active' => true]);
    }
}
