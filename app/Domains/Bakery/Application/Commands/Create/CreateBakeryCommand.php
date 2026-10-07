<?php

namespace Domains\Bakery\Application\Commands\Create;

use Shared\Bus\BaseCommand;

readonly class CreateBakeryCommand implements BaseCommand
{
    public function __construct(
        public string $name,
    ) {}
}
