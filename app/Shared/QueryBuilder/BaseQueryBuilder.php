<?php

namespace Shared\QueryBuilder;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @template TModel of Model
 */
class BaseQueryBuilder
{
    /** @var Builder<TModel> */
    private Builder $query;

    /**
     * @param  class-string<TModel>  $modelClass
     */
    public function __construct(string $modelClass)
    {
        /** @var TModel $model */
        $model = new $modelClass;
        /** @var Builder<TModel> $query */
        $query = $model->newQuery();
        $this->query = $query;
    }

    /**
     * @param  list<string>  $columns
     */
    public function select(array $columns): static
    {
        $this->query->select($columns);

        return $this;
    }

    /**
     * @param  array<string>  $relations
     */
    public function with(array $relations): static
    {
        $this->query->with($relations);

        return $this;
    }

    public function where(string $column, mixed $value): static
    {
        $this->query->where($column, $value);

        return $this;
    }

    /**
     * @param  array<int|string, mixed>  $values
     */
    public function whereIn(string $column, array $values): static
    {
        $this->query->whereIn($column, $values);

        return $this;
    }

    /**
     * @param  array{0: mixed, 1: mixed}  $range
     */
    public function whereBetween(string $column, array $range): static
    {
        $this->query->whereBetween($column, $range);

        return $this;
    }

    /**
     * @param  'asc'|'desc'  $direction
     */
    public function orderBy(string $column, string $direction = 'asc'): static
    {
        $this->query->orderBy($column, $direction);

        return $this;
    }

    /**
     * @return Builder<TModel>
     */
    public function getQuery(): Builder
    {
        return $this->query;
    }
}
