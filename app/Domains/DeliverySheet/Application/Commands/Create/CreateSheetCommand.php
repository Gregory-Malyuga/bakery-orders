<?php

namespace Domains\DeliverySheet\Application\Commands\Create;

use Shared\Bus\BaseCommand;

readonly class CreateSheetCommand implements BaseCommand
{
    /**
     * @param array{date:string,name:string,driver:string,salary?:string,bonus?:string,
     *     stores:list<int|string>} $data
     */
    public function __construct(public array $data) {}
}
