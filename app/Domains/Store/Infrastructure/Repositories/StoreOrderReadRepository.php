<?php

namespace Domains\Store\Infrastructure\Repositories;

use Domains\Bakery\Domain\Models\Bakery;
use Domains\Store\Application\Repositories\StoreOrderReadRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

final class StoreOrderReadRepository implements StoreOrderReadRepositoryInterface
{
    public function find(int $id): ?Bakery
    {
        return Bakery::query()->find($id);
    }

    /** @return Collection<int, Bakery> */
    public function all(): Collection
    {
        return Bakery::query()->orderBy('name')->get();
    }

    public function lockForUpdate(int $id): void
    {
        Bakery::query()->whereKey($id)->lockForUpdate()->firstOrFail();
    }
}
