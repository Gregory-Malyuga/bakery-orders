<?php

namespace Domains\Price\Infrastructure\Repositories;

use Domains\Bakery\Application\Repositories\OrderingRepositoryInterface;
use Domains\Price\Application\Repositories\PriceAvailabilityReadRepositoryInterface;

final class PriceAvailabilityReadRepository implements PriceAvailabilityReadRepositoryInterface
{
    public function __construct(private readonly OrderingRepositoryInterface $ordering) {}

    public function hasPrice(int $storeId, int $productId): bool
    {
        return isset($this->ordering->prices($storeId, true)[$productId]);
    }
}
