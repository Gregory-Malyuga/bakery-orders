<?php

namespace Domains\StoreAccount\Infrastructure\Repositories;

use Domains\StoreAccount\Application\Repositories\StoreOwnerReadRepositoryInterface;
use Illuminate\Support\Facades\DB;

final class StoreOwnerReadRepository implements StoreOwnerReadRepositoryInterface
{
    /** @return array<int|string, mixed> */
    public function storeOwners(): array
    {
        return DB::table('bakery_account_stores')->pluck('user_id', 'bakery_id')->all();
    }
}
