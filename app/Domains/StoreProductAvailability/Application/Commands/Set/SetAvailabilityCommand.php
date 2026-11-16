<?php

namespace Domains\StoreProductAvailability\Application\Commands\Set;

use Shared\Bus\BaseCommand;

readonly class SetAvailabilityCommand implements BaseCommand
{
    public function __construct(
        public int $storeId,
        public int $productId,
        public bool $isAvailable,
    ) {}
}
