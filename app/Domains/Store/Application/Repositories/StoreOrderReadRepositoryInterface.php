<?php

namespace Domains\Store\Application\Repositories;

use Domains\Bakery\Domain\Models\Bakery;
use Illuminate\Database\Eloquent\Collection;

interface StoreOrderReadRepositoryInterface
{
    public function find(int $id): ?Bakery;

    /** @return Collection<int, Bakery> */
    public function all(): Collection;

    public function lockForUpdate(int $id): void;
}
