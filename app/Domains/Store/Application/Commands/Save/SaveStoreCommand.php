<?php

namespace Domains\Store\Application\Commands\Save;

use Shared\Bus\BaseCommand;

readonly class SaveStoreCommand implements BaseCommand
{
    public function __construct(
        public ?int $id,
        public string $name,
        public string $address,
        public bool $isActive,
        public ?bool $isCashless,
        public int $discountPercent,
    ) {}
}
