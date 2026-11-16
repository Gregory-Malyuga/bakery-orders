<?php

namespace Domains\StockItem\Infrastructure\Repositories;

use Domains\StockItem\Application\Repositories\IngredientStockReadRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class IngredientStockReadRepository implements IngredientStockReadRepositoryInterface
{
    /** @return Collection<int, \stdClass> */
    public function ingredients(): Collection
    {
        return DB::table('bakery_stock_items')->where('kind', 'ingredient')->orderBy('name')->get();
    }
}
