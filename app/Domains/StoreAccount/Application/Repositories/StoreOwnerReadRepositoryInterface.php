<?php

namespace Domains\StoreAccount\Application\Repositories;

interface StoreOwnerReadRepositoryInterface
{
    /** @return array<int|string, mixed> */
    /** @return array<int, int> */
    public function storeOwners(): array;
}
