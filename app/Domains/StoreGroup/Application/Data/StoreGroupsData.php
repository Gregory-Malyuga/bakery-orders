<?php

namespace Domains\StoreGroup\Application\Data;

use Illuminate\Support\Collection;
use Shared\Http\Data\BaseData;

class StoreGroupsData extends BaseData
{
    /**
     * @param  Collection<int, \stdClass>  $groups
     * @param  Collection<int|string, Collection<int, \stdClass>>  $members
     */
    public function __construct(public readonly Collection $groups, public readonly Collection $members) {}
}
