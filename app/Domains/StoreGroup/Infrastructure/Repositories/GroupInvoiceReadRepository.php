<?php

namespace Domains\StoreGroup\Infrastructure\Repositories;

use Domains\StoreGroup\Application\Repositories\GroupInvoiceReadRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class GroupInvoiceReadRepository implements GroupInvoiceReadRepositoryInterface
{
    /** @return array<int,list<object{group_id:int|string,bakery_id:int|string,name:string,address:string}>> */
    public function membersByGroup(): array
    {
        $membersByGroup = [];
        foreach (DB::table('bakery_store_group_members as members')
            ->join('bakeries as stores', 'stores.id', '=', 'members.bakery_id')
            ->select('members.group_id', 'members.bakery_id', 'stores.name', 'stores.address')
            ->orderBy('stores.name')->get() as $member) {
            /** @var object{group_id:int|string,bakery_id:int|string,name:string,address:string} $member */
            $membersByGroup[(int) $member->group_id][] = $member;
        }

        return $membersByGroup;
    }

    /** @return Collection<int, \stdClass> */
    public function invoiceGroups(): Collection
    {
        return DB::table('bakery_store_groups')->where('show_in_invoice', true)->orderBy('name')->get();
    }
}
