<?php

namespace Domains\Price\Application\Repositories;

interface PriceAvailabilityReadRepositoryInterface
{
    public function hasPrice(int $storeId, int $productId): bool;
}
