<?php

namespace Domains\Product\Application\Repositories;

use Domains\Bakery\Domain\Models\Product;
use Illuminate\Database\Eloquent\Collection;

interface ProductReferenceImportRepositoryInterface
{
    /** @return Collection<int, Product> */
    public function all(): Collection;

    public function activateOrCreate(string $name, int $position, string $packagingMode, int $minimum): Product;

    public function activate(int $productId, int $position): void;
}
