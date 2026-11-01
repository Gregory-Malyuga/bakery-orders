<?php

namespace Domains\User\Infrastructure\Filters;

use Illuminate\Database\Eloquent\Builder;
use Shared\Filters\FilterInterface;

class EmailFilter implements FilterInterface
{
    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function apply(Builder $query, mixed $value): Builder
    {
        if (! is_string($value) || $value === '') {
            return $query;
        }

        return $query->whereRaw('LOWER(email) LIKE ?', ['%'.strtolower($value).'%']);
    }
}
