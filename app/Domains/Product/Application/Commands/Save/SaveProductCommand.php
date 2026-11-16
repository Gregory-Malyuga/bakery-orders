<?php

namespace Domains\Product\Application\Commands\Save;

use Shared\Bus\BaseCommand;

readonly class SaveProductCommand implements BaseCommand
{
    public function __construct(
        public ?int $id,
        public string $name,
        public int $sortOrder,
        public int $minimumQuantity,
        public ?string $packagingMode,
        public bool $isActive,
    ) {}
}
