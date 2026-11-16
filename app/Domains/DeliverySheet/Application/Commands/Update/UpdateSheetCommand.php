<?php

namespace Domains\DeliverySheet\Application\Commands\Update;

use Shared\Bus\BaseCommand;

readonly class UpdateSheetCommand implements BaseCommand
{
    /** @param array{date:string,name:string,driver:string,salary?:string,bonus?:string,stores:list<int|string>} $data */
    public function __construct(public int $sheetId, public array $data) {}
}
