<?php

namespace Domains\StoreGroup\Application\Repositories;

use Illuminate\Support\Collection;

interface GroupInvoiceReadRepositoryInterface
{
    /** @return array<int,list<object{group_id:int|string,bakery_id:int|string,name:string,address:string}>> */
    public function membersByGroup(): array;

    /** @return Collection<int, \stdClass> */
    public function invoiceGroups(): Collection;
}
