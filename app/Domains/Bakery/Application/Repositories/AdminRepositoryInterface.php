<?php

namespace Domains\Bakery\Application\Repositories;

interface AdminRepositoryInterface
{
    /** @param array{name:string,address:string,is_active:bool} $data */
    public function saveStore(?int $id, array $data): void;

    /** @param array{name:string,sort_order:int,is_active:bool} $data */
    public function saveProduct(?int $id, array $data): void;

    public function savePrice(int $storeId, int $productId, int $kopecks): void;

    public function saveAccount(?int $id, int $storeId, string $name, string $email, ?string $password, bool $active): void;

    /** @return list<array{id:int,bakery_id:int,name:string,email:string,status:string}> */
    public function accounts(): array;
}
