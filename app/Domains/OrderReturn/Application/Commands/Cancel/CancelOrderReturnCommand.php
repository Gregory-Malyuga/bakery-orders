<?php

namespace Domains\OrderReturn\Application\Commands\Cancel;

use Shared\Bus\BaseCommand;

readonly class CancelOrderReturnCommand implements BaseCommand
{
    public function __construct(
        public int $orderId,
        public int $returnId,
        public int $adminId,
        public string $reason,
    ) {}
}
