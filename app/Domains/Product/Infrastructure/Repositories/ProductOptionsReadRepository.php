<?php

namespace Domains\Product\Infrastructure\Repositories;

use Domains\Product\Application\Repositories\ProductOptionsReadRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class ProductOptionsReadRepository implements ProductOptionsReadRepositoryInterface
{
    /** @return Collection<int, \stdClass> */
    public function productOptions(): Collection
    {
        return DB::table('bakery_products')->orderBy('name')->get(['id', 'name']);
    }
}
