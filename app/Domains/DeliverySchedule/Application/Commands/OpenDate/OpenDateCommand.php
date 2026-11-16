<?php

namespace Domains\DeliverySchedule\Application\Commands\OpenDate;

use Shared\Bus\BaseCommand;

readonly class OpenDateCommand implements BaseCommand
{
    public function __construct(public string $date) {}
}
