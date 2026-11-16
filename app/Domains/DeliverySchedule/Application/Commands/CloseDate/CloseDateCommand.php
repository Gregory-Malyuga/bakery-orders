<?php

namespace Domains\DeliverySchedule\Application\Commands\CloseDate;

use Shared\Bus\BaseCommand;

readonly class CloseDateCommand implements BaseCommand
{
    public function __construct(
        public string $date,
        public string $reason,
        public bool $keepExistingOrders = false,
    ) {}
}
