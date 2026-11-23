<?php

namespace Domains\Catalog\Application\Repositories;

use Illuminate\Support\Collection;

interface CatalogReadRepositoryInterface
{
    /** @return array<int, int> */
    public function storeOwners(): array;

    /** @return array<int, bool> */
    public function unavailableProducts(int $storeId): array;

    /** @return array<int, bool> */
    public function unavailableStores(int $productId): array;

    /** @return Collection<int, \stdClass> */
    public function priceOperations(int $productId): Collection;

    /** @param Collection<int, \stdClass> $history
     * @return Collection<int|string, Collection<int, \stdClass>>
     */
    public function priceOperationItems(Collection $history): Collection;

    /** @param Collection<int, \stdClass> $history
     * @return list<int|string>
     */
    public function reversedOperationIds(Collection $history): array;
}
