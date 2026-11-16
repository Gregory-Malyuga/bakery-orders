<?php

namespace Domains\Ingredient\Application\Commands\Rename;

use Shared\Bus\BaseCommand;

readonly class RenameIngredientCommand implements BaseCommand
{
    public function __construct(public int $id, public string $name) {}
}
