<?php

namespace Domains\Bakery\Application\Repositories;

use Domains\Bakery\Domain\Models\Bakery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Shared\Data\PaginationData;
use Shared\Data\SortData;
use Spatie\LaravelData\Data;

interface BakeryRepositoryInterface
{
    public function findOrFail(int|string $id): Bakery;

    /** @param array<string, mixed> $data */
    public function create(array $data): Bakery;

    /** @param array<string, mixed> $data */
    public function update(Bakery $record, array $data): Bakery;

    public function delete(Bakery $record): void;

    /** @return LengthAwarePaginator<int, Bakery> */
    public function list(Data $filters, ?SortData $sort = null, ?PaginationData $pagination = null): LengthAwarePaginator;
}
