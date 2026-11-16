<?php

namespace Domains\Ingredient\Application\Commands\Create;

use Shared\Bus\BaseCommand;

readonly class CreateIngredientCommand implements BaseCommand
{
    public function __construct(public string $name, public string $unit) {}
}
