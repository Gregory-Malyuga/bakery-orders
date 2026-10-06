<?php

namespace Shared\QueryBuilder;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Shared\Data\PaginationData;
use Shared\Data\SortData;

class BaseQueryBuilderPostprocessor
{
    /**
     * @param  Builder<Model>  $query
     * @return LengthAwarePaginator<int, Model>
     */
    public function process(
        Builder $query,
        SortData $sort,
        PaginationData $pagination,
    ): LengthAwarePaginator {
        /** @var 'asc'|'desc' $direction */
        $direction = $sort->direction;

        return $query
            ->orderBy($sort->field, $direction)
            ->paginate($pagination->perPage, ['*'], 'page', $pagination->page);
    }
}
