<?php

namespace Domains\Product\Infrastructure\Repositories;

use Domains\Bakery\Domain\Models\Product;
use Domains\Product\Application\Repositories\ProductOrderReadRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

final class ProductOrderReadRepository implements ProductOrderReadRepositoryInterface
{
    /** @return Collection<int, Product> */
    public function all(): Collection
    {
        return Product::query()->orderBy('sort_order')->orderBy('id')->get();
    }
}
