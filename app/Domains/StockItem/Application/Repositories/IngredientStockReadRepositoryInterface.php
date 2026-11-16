<?php

namespace Domains\StockItem\Application\Repositories;

use Illuminate\Support\Collection;

interface IngredientStockReadRepositoryInterface
{
    /** @return Collection<int, \stdClass> */
    public function ingredients(): Collection;
}
