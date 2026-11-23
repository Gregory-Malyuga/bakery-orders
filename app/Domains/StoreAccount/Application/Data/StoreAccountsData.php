<?php

namespace Domains\StoreAccount\Application\Data;

use Shared\Http\Data\BaseData;

class StoreAccountsData extends BaseData
{
    /**
     * @param list<array{id:int,bakery_id:int|null,store_ids:list<int>,name:string,email:string,
     *     status:string}> $accounts
     */
    public function __construct(public readonly array $accounts) {}
}
