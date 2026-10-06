<?php

namespace Shared\Filters;

use Illuminate\Database\Eloquent\Builder;

class NoOpFilter implements FilterInterface
{
    /** @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function apply(Builder $query, mixed $value): Builder
    {
        return $query;
    }
}
