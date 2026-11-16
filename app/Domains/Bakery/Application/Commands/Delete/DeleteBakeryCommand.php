<?php

namespace Domains\Bakery\Application\Commands\Delete;

use Shared\Bus\BaseCommand;

readonly class DeleteBakeryCommand implements BaseCommand
{
    public function __construct(public int $id) {}
}
