<?php

namespace Domains\StoreAccount\Application\Commands\Save;

use Shared\Bus\BaseCommand;

readonly class SaveStoreAccountCommand implements BaseCommand
{
    /** @param list<int> $storeIds */
    public function __construct(
        public ?int $id,
        public array $storeIds,
        public string $name,
        public string $email,
        public ?string $password,
        public bool $isActive,
    ) {}
}
