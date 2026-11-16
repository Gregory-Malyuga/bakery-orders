<?php

namespace Domains\OrderAdjustment\Application\Commands\Adjust;

use Shared\Bus\BaseCommand;

readonly class AdjustOrderCommand implements BaseCommand
{
    /** @param array<int|string,int|string> $quantities */
    public function __construct(
        public int $orderId,
        public int $adminId,
        public array $quantities,
        public string $reason,
        public ?string $adminNote,
    ) {}
}
