<?php

namespace Domains\DeliverySheet\Application\Commands\Print;

use Shared\Bus\BaseCommand;

readonly class PrintSheetCommand implements BaseCommand
{
    public function __construct(public int $sheetId) {}
}
