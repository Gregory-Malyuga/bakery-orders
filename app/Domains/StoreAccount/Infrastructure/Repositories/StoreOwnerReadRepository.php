<?php

namespace Domains\StoreAccount\Infrastructure\Repositories;

use Domains\StoreAccount\Application\Repositories\StoreOwnerReadRepositoryInterface;
use Illuminate\Support\Facades\DB;

final class StoreOwnerReadRepository implements StoreOwnerReadRepositoryInterface
{
    /** @return array<int, int> */
    public function storeOwners(): array
    {
        $owners = [];
        foreach (DB::table('bakery_account_stores')->pluck('user_id', 'bakery_id') as $storeId => $userId) {
            if (! is_int($userId) && ! is_string($userId)) {
                throw new \UnexpectedValueException('Invalid store owner ID');
            }
            $owners[(int) $storeId] = (int) $userId;
        }

        return $owners;
    }
}
