<?php

namespace Domains\Store\Infrastructure\Repositories;

use Domains\Bakery\Domain\Models\Bakery;
use Domains\Store\Application\Repositories\StoreReferenceImportRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

final class StoreReferenceImportRepository implements StoreReferenceImportRepositoryInterface
{
    /** @return Collection<int, Bakery> */
    public function all(): Collection
    {
        return Bakery::query()->get();
    }

    public function activateOrCreate(string $name, string $address): Bakery
    {
        $store = Bakery::query()->firstOrCreate(['name' => $name], [
            'address' => $address,
            'is_active' => true,
        ]);
        $store->update(['is_active' => true]);

        return $store;
    }
    public function activate(int $storeId): void
    {
        Bakery::query()->whereKey($storeId)->update(['is_active' => true]);
    }
}
