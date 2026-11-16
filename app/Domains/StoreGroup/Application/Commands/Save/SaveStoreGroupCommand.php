<?php

namespace Domains\StoreGroup\Application\Commands\Save;

use Shared\Bus\BaseCommand;

readonly class SaveStoreGroupCommand implements BaseCommand
{
    /** @param list<int> $stores */
    public function __construct(
        public ?int $id,
        public string $name,
        public bool $showInInvoice,
        public bool $excludeFromLabels,
        public array $stores,
    ) {}
}
