<?php

namespace Domains\Bakery\Application\Queries\ListAll;

use Shared\Bus\ListEntityQuery;

class ListBakerysQuery extends ListEntityQuery
{
    /** @var list<string> */
    public const array SORTABLE = ['id'];
}
