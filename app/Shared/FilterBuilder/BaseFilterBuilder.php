<?php

namespace Shared\FilterBuilder;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionProperty;
use Shared\Filters\FilterInterface;
use Spatie\LaravelData\Data;

class BaseFilterBuilder
{
    /**
     * @param  Builder<Model>  $query
     * @param  array<string, class-string<FilterInterface>>  $filterMap
     * @return Builder<Model>
     */
    public function apply(
        Builder $query,
        Data $filters,
        array $filterMap = [],
    ): Builder {
        $properties = (new ReflectionClass($filters))->getProperties(ReflectionProperty::IS_PUBLIC);

        foreach ($properties as $property) {
            $key = $property->getName();
            $value = $property->getValue($filters);

            if ($value === null) {
                continue;
            }

            if (isset($filterMap[$key])) {
                /** @var FilterInterface $filter */
                $filter = app($filterMap[$key]);
                $query = $filter->apply($query, $value);
            } else {
                $query->where(Str::snake($key), $value);
            }
        }

        return $query;
    }
}
