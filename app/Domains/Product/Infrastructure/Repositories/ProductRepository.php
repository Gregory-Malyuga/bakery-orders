<?php

namespace Domains\Product\Infrastructure\Repositories;

use Domains\Product\Domain\Models\Product;
use Domains\Product\Application\Repositories\ProductRepositoryInterface;
use Domains\StoreProductMinimum\Application\Repositories\StoreProductMinimumRepositoryInterface;
use Illuminate\Support\Facades\DB;

final class ProductRepository implements ProductRepositoryInterface
{
    public function __construct(private readonly StoreProductMinimumRepositoryInterface $minimums) {}

    public function packagingMode(int $id): ?string
    {
        $mode = Product::query()->whereKey($id)->value('packaging_mode');

        return is_string($mode) ? $mode : null;
    }

    public function save(?int $id, array $changes, int $minimumForPackaging): void
    {
        DB::transaction(function () use ($id, $changes, $minimumForPackaging): void {
            Product::query()->updateOrCreate(['id' => $id], $changes);

            if ($id !== null) {
                $this->minimums->normalizeForProduct($id, $minimumForPackaging);
            }
        });
    }
}
