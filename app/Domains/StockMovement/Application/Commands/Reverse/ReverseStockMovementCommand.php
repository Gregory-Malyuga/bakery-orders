<?php

namespace Domains\StockMovement\Application\Commands\Reverse;

use Shared\Bus\BaseCommand;

readonly class ReverseStockMovementCommand implements BaseCommand
{
    public function __construct(
        public int $movementId,
        public string $reason,
        public int $actorId,
    ) {}
}
