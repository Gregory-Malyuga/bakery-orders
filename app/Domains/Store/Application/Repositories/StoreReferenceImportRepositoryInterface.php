<?php

namespace Domains\Store\Application\Repositories;

use Domains\Bakery\Domain\Models\Bakery;
use Illuminate\Database\Eloquent\Collection;

interface StoreReferenceImportRepositoryInterface
{
    /** @return Collection<int, Bakery> */
    public function all(): Collection;

    public function activateOrCreate(string $name, string $address): Bakery;

    public function activate(int $storeId): void;
}
