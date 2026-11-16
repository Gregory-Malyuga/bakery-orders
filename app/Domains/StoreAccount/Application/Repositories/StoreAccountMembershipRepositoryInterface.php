<?php

namespace Domains\StoreAccount\Application\Repositories;

interface StoreAccountMembershipRepositoryInterface
{
    public function ensureMember(int $userId, int $storeId): void;
}
