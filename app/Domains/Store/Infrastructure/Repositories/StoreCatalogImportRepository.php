<?php

namespace Domains\Store\Infrastructure\Repositories;

use Domains\Store\Application\Repositories\StoreCatalogImportRepositoryInterface;
use Domains\Store\Domain\Models\Store;

final class StoreCatalogImportRepository implements StoreCatalogImportRepositoryInterface
{
    public function firstOrCreate(string $name, string $address): int
    {
        return (int) Store::query()->firstOrCreate(
            ['name' => $name, 'address' => $address], ['is_active' => true],
        )->id;
    }
}
