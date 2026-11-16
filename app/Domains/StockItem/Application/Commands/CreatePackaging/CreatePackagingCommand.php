<?php

namespace Domains\StockItem\Application\Commands\CreatePackaging;

use Shared\Bus\BaseCommand;

readonly class CreatePackagingCommand implements BaseCommand
{
    public function __construct(public string $name, public string $unit) {}
}
