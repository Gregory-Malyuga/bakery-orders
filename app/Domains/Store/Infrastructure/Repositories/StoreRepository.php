<?php

namespace Domains\Store\Infrastructure\Repositories;

use Domains\Store\Application\Repositories\StoreRepositoryInterface;
use Domains\Store\Domain\Models\Store;

final class StoreRepository implements StoreRepositoryInterface
{
    public function save(?int $id, array $attributes): void
    {
        Store::query()->updateOrCreate(['id' => $id], $attributes);
    }
}
