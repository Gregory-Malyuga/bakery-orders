<?php

namespace Domains\Store\Infrastructure\Repositories;

use Domains\Store\Application\Repositories\StoreReferenceImportRepositoryInterface;
use Domains\Store\Domain\Models\Store;
use Illuminate\Database\Eloquent\Collection;

final class StoreReferenceImportRepository implements StoreReferenceImportRepositoryInterface
{
    /** @return Collection<int, Store> */
    public function all(): Collection
    {
        return Store::query()->get();
    }

    public function activateOrCreate(string $name, string $address): Store
    {
        $store = Store::query()->firstOrCreate(['name' => $name], [
            'address' => $address,
            'is_active' => true,
        ]);
        $store->update(['is_active' => true]);

        return $store;
    }

    public function activate(int $storeId): void
    {
        Store::query()->whereKey($storeId)->update(['is_active' => true]);
    }
}
