<?php

namespace Domains\StoreAccount\Application\Repositories;

interface StoreOwnerReadRepositoryInterface
{
    /** @return array<int|string, mixed> */
    public function storeOwners(): array;
}
