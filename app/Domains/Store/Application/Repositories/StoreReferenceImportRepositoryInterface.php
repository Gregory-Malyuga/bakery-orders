<?php

namespace Domains\Store\Application\Repositories;

use Domains\Store\Domain\Models\Store;
use Illuminate\Database\Eloquent\Collection;

interface StoreReferenceImportRepositoryInterface
{
    /** @return Collection<int, Store> */
    public function all(): Collection;

    public function activateOrCreate(string $name, string $address): Store;

    public function activate(int $storeId): void;
}
