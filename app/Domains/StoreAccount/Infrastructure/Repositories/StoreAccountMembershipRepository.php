<?php

namespace Domains\StoreAccount\Infrastructure\Repositories;

use Domains\StoreAccount\Application\Repositories\StoreAccountMembershipRepositoryInterface;
use Illuminate\Support\Facades\DB;

final class StoreAccountMembershipRepository implements StoreAccountMembershipRepositoryInterface
{
    public function ensureMember(int $userId, int $storeId): void
    {
        DB::table('bakery_account_stores')->updateOrInsert([
            'user_id' => $userId,
            'bakery_id' => $storeId,
        ]);
    }
}
