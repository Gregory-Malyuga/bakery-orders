<?php

namespace Shared\Bus;

use Shared\Data\PaginationData;
use Shared\Data\SortData;
use Spatie\LaravelData\Data;

/** @phpstan-consistent-constructor */
abstract class ListEntityQuery implements BaseQuery
{
    /** @var list<string> */
    public const array SORTABLE = ['id'];

    public function __construct(
        public readonly Data $filters,
        public readonly SortData $sort,
        public readonly PaginationData $pagination,
    ) {}
}
