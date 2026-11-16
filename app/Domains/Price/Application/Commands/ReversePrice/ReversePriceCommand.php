<?php

namespace Domains\Price\Application\Commands\ReversePrice;

use Shared\Bus\BaseCommand;

readonly class ReversePriceCommand implements BaseCommand
{
    public function __construct(
        public int $productId,
        public int $operationId,
        public string $reason,
        public int $actorId,
    ) {}
}
