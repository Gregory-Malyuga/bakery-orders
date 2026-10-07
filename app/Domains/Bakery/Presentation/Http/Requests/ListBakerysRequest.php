<?php

namespace Domains\Bakery\Presentation\Http\Requests;

use Domains\Bakery\Application\Data\BakeryFilterData;
use Domains\Bakery\Application\Queries\ListAll\ListBakerysQuery;
use Shared\Http\Requests\ListRequest;

class ListBakerysRequest extends ListRequest
{
    protected const array SORTABLE = ListBakerysQuery::SORTABLE;

    public function toFilters(): BakeryFilterData
    {
        return BakeryFilterData::from($this->all());
    }
}
