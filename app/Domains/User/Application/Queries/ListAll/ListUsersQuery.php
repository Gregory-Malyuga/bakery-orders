<?php

namespace Domains\User\Application\Queries\ListAll;

use Shared\Bus\ListEntityQuery;

class ListUsersQuery extends ListEntityQuery
{
    /** @var list<string> */
    public const array SORTABLE = ['id', 'name', 'email', 'created_at'];
}
