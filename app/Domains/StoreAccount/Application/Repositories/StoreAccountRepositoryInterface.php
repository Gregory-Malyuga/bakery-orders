<?php

namespace Domains\StoreAccount\Application\Repositories;

interface StoreAccountRepositoryInterface
{
    /** @param list<int> $storeIds */
    public function saveAccount(
        ?int $id,
        array $storeIds,
        string $name,
        string $email,
        ?string $password,
        bool $active,
    ): void;

    /** @return list<array{id:int,bakery_id:int|null,store_ids:list<int>,name:string,email:string,status:string}> */
    public function accounts(): array;
}
