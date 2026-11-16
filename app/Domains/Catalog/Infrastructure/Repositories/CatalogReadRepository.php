<?php

namespace Domains\Catalog\Infrastructure\Repositories;

use Domains\Catalog\Application\Repositories\CatalogReadRepositoryInterface;
use Domains\Price\Application\Repositories\PriceHistoryCatalogReadRepositoryInterface;
use Domains\StoreAccount\Application\Repositories\StoreOwnerReadRepositoryInterface;
use Domains\StoreProductAvailability\Application\Repositories\AvailabilityCatalogReadRepositoryInterface;
use Illuminate\Support\Collection;

/**
 * Catalog is a composite read model; persistence stays with entity-owning domains.
 */
final class CatalogReadRepository implements CatalogReadRepositoryInterface
{
    public function __construct(
        private readonly StoreOwnerReadRepositoryInterface $accounts,
        private readonly AvailabilityCatalogReadRepositoryInterface $availability,
        private readonly PriceHistoryCatalogReadRepositoryInterface $prices,
    ) {}

    /** @return array<int|string, mixed> */
    public function storeOwners(): array
    {
        return $this->accounts->storeOwners();
    }

    /** @return array<int, bool> */
    public function unavailableProducts(int $storeId): array
    {
        return $this->availability->unavailableProducts($storeId);
    }

    /** @return array<int, bool> */
    public function unavailableStores(int $productId): array
    {
        return $this->availability->unavailableStores($productId);
    }

    /** @return Collection<int, \stdClass> */
    public function priceOperations(int $productId): Collection
    {
        return $this->prices->priceOperations($productId);
    }

    /** @param Collection<int, \stdClass> $history
     * @return Collection<int|string, Collection<int, \stdClass>>
     */
    public function priceOperationItems(Collection $history): Collection
    {
        return $this->prices->priceOperationItems($history);
    }

    /** @param Collection<int, \stdClass> $history
     * @return list<int|string>
     */
    public function reversedOperationIds(Collection $history): array
    {
        return $this->prices->reversedOperationIds($history);
    }
}
