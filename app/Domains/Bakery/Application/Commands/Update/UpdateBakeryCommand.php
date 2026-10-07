<?php

namespace Domains\Bakery\Application\Commands\Update;

use Shared\Bus\BaseCommand;

readonly class UpdateBakeryCommand implements BaseCommand
{
    public function __construct(
        public int $id,
        public string $name,
    ) {}
}
