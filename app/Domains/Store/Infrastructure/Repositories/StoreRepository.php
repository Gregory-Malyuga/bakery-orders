<?php

namespace Domains\Store\Infrastructure\Repositories;

use Domains\Store\Domain\Models\Store;
use Domains\Store\Application\Repositories\StoreRepositoryInterface;

final class StoreRepository implements StoreRepositoryInterface
{
    public function save(?int $id, array $attributes): void
    {
        Store::query()->updateOrCreate(['id' => $id], $attributes);
    }
}
