<?php

namespace Domains\Ingredient\Application\Commands\Toggle;

use Shared\Bus\BaseCommand;

readonly class ToggleIngredientCommand implements BaseCommand
{
    public function __construct(public int $id) {}
}
