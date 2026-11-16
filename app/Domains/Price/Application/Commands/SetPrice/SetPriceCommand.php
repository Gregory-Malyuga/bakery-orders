<?php

namespace Domains\Price\Application\Commands\SetPrice;

use Shared\Bus\BaseCommand;

readonly class SetPriceCommand implements BaseCommand
{
    public function __construct(
        public int $productId,
        public int $storeId,
        public int $kopecks,
        public int $actorId,
    ) {}
}
