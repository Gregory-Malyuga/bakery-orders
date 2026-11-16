<?php

namespace Domains\StockMovement\Application\Commands\Record;

use Shared\Bus\BaseCommand;

readonly class RecordStockMovementCommand implements BaseCommand
{
    public function __construct(
        public int $itemId,
        public string $kind,
        public string $quantity,
        public string $reason,
        public int $actorId,
    ) {}
}
