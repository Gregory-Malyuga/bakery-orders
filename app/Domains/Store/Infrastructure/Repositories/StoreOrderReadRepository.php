<?php

namespace Domains\Store\Infrastructure\Repositories;

use Domains\Store\Application\Repositories\StoreOrderReadRepositoryInterface;
use Domains\Store\Domain\Models\Store;
use Illuminate\Database\Eloquent\Collection;

final class StoreOrderReadRepository implements StoreOrderReadRepositoryInterface
{
    public function find(int $id): ?Store
    {
        return Store::query()->find($id);
    }

    /** @return Collection<int, Store> */
    public function all(): Collection
    {
        return Store::query()->orderBy('name')->get();
    }

    public function lockForUpdate(int $id): void
    {
        Store::query()->whereKey($id)->lockForUpdate()->firstOrFail();
    }
}
