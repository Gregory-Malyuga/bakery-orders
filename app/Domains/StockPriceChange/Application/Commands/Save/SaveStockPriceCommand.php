<?php

namespace Domains\StockPriceChange\Application\Commands\Save;

use Shared\Bus\BaseCommand;

readonly class SaveStockPriceCommand implements BaseCommand
{
    public function __construct(
        public int $itemId,
        public string $price,
        public string $measure,
        public ?string $customQuantity,
        public ?string $customUnit,
        public int $actorId,
    ) {}
}
