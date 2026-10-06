<?php

namespace Domains\User\Presentation\Http\Requests;

use Domains\User\Application\Data\UserFilterData;
use Domains\User\Application\Queries\ListAll\ListUsersQuery;
use Shared\Http\Requests\ListRequest;

class ListUsersRequest extends ListRequest
{
    protected const array SORTABLE = ListUsersQuery::SORTABLE;

    public function toFilters(): UserFilterData
    {
        return UserFilterData::from($this->all());
    }
}
