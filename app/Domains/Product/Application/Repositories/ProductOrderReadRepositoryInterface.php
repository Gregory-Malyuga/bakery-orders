<?php

namespace Domains\Product\Application\Repositories;

use Domains\Product\Domain\Models\Product;
use Illuminate\Database\Eloquent\Collection;

interface ProductOrderReadRepositoryInterface
{
    /** @return Collection<int, Product> */
    public function all(): Collection;
}
