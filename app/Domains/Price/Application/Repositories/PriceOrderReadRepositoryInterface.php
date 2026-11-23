<?php

namespace Domains\Price\Application\Repositories;

interface PriceOrderReadRepositoryInterface
{
    /** @return array<int, int> */
    public function forStore(
        int $storeId,
        bool $includeUnavailable = false,
        bool $applyDiscount = true,
    ): array;
}
