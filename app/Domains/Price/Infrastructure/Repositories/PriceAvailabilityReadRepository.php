<?php

namespace Domains\Price\Infrastructure\Repositories;

use Domains\Order\Application\Repositories\OrderRepositoryInterface;
use Domains\Price\Application\Repositories\PriceAvailabilityReadRepositoryInterface;

final class PriceAvailabilityReadRepository implements PriceAvailabilityReadRepositoryInterface
{
    public function __construct(private readonly OrderRepositoryInterface $ordering) {}

    public function hasPrice(int $storeId, int $productId): bool
    {
        return isset($this->ordering->prices($storeId, true)[$productId]);
    }
}
