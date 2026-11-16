<?php

namespace Domains\OrderReturn\Application\Commands\Record;

use Shared\Bus\BaseCommand;

readonly class RecordOrderReturnCommand implements BaseCommand
{
    public function __construct(
        public int $orderId,
        public int $adminId,
        public int $productId,
        public int $quantity,
        public ?string $reason,
    ) {}
}
