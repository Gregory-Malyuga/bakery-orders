<?php

namespace Domains\StoreProductMinimum\Application\Commands\Save;

use Shared\Bus\BaseCommand;

readonly class SaveMinimumCommand implements BaseCommand
{
    public function __construct(
        public int $storeId,
        public int $productId,
        public ?int $minimum,
    ) {}
}
