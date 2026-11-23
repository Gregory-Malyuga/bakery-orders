<?php

namespace Domains\StoreGroup\Infrastructure\Repositories;

use Domains\StoreGroup\Application\Repositories\LabelGroupExclusionRepositoryInterface;
use Illuminate\Support\Facades\DB;

final class LabelGroupExclusionRepository implements LabelGroupExclusionRepositoryInterface
{
    /** @return array<int,list<string>> */
    public function excludedStores(): array
    {
        $exclusions = [];
        foreach (
            DB::table('bakery_store_group_members as members')
                ->join('bakery_store_groups as groups', 'groups.id', '=', 'members.group_id')
                ->where('groups.exclude_from_labels', true)
                ->select('members.bakery_id', 'groups.name')
                ->orderBy('groups.name')
                ->get() as $row
        ) {
            /** @var object{bakery_id:int|string,name:string} $row */
            $exclusions[(int) $row->bakery_id][] = $row->name;
        }

        return $exclusions;
    }
}
