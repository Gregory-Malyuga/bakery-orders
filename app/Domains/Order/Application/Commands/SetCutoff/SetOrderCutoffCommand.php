<?php

namespace Domains\Order\Application\Commands\SetCutoff;

use Shared\Bus\BaseCommand;

readonly class SetOrderCutoffCommand implements BaseCommand
{
    public function __construct(public string $time) {}
}
