<?php

namespace Domains\Price\Application\Repositories;

use Illuminate\Support\Collection;

interface PriceHistoryCatalogReadRepositoryInterface
{
    /** @return Collection<int, \stdClass> */
    public function priceOperations(int $productId): Collection;

    /**
     * @param  Collection<int, \stdClass>  $history
     * @return Collection<int|string, Collection<int, \stdClass>>
     */
    public function priceOperationItems(Collection $history): Collection;

    /** @param Collection<int, \stdClass> $history
     * @return list<int|string>
     */
    public function reversedOperationIds(Collection $history): array;
}
