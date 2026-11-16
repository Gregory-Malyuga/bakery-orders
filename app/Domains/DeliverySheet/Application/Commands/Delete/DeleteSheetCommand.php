<?php

namespace Domains\DeliverySheet\Application\Commands\Delete;

use Shared\Bus\BaseCommand;

readonly class DeleteSheetCommand implements BaseCommand
{
    public function __construct(public int $sheetId) {}
}
