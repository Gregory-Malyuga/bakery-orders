<?php

namespace Domains\Price\Application\Commands\BulkPrice;

use Shared\Bus\BaseCommand;

readonly class BulkPriceCommand implements BaseCommand
{
    /** @param list<int> $storeIds */
    public function __construct(
        public int $productId,
        public array $storeIds,
        public int $deltaKopecks,
        public string $direction,
        public string $requestKey,
        public int $actorId,
    ) {}
}
