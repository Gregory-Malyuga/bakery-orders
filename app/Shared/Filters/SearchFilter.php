<?php

namespace Shared\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class SearchFilter implements FilterInterface
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

        $table = $query->getModel()->getTable();

        if (Schema::hasColumn($table, 'name')) {
            return $query->whereRaw('LOWER(name) LIKE ?', ['%'.strtolower($value).'%']);
        }

        return $query->where('id', $value);
    }
}
