<?php

namespace Shared\Http\Data;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\LaravelData\DataCollection;
use Spatie\LaravelData\PaginatedDataCollection;

/**
 * @template TKey of array-key
 * @template TValue
 *
 * @extends DataCollection<TKey, TValue>
 */
class BaseDataCollection extends DataCollection
{
    /**
     * @template T of BaseData
     *
     * @param  class-string<T>  $dataClass
     * @param  LengthAwarePaginator<int, mixed>  $paginator
     * @return PaginatedDataCollection<int, T>
     */
    public static function fromPaginator(string $dataClass, LengthAwarePaginator $paginator): PaginatedDataCollection
    {
        return new PaginatedDataCollection($dataClass, $paginator);
    }
}
