<?php

namespace Domains\Store\Application\Repositories;

use Domains\Store\Domain\Models\Store;
use Illuminate\Database\Eloquent\Collection;

interface StoreOrderReadRepositoryInterface
{
    public function find(int $id): ?Store;

    /** @return Collection<int, Store> */
    public function all(): Collection;

    public function lockForUpdate(int $id): void;
}
