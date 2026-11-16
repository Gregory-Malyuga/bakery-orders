<?php

namespace Domains\Recipe\Application\Commands\SaveProduct;

use Shared\Bus\BaseCommand;

readonly class SaveProductRecipeCommand implements BaseCommand
{
    /** @param array{product:int|string,dough_type?:string,dough_grams?:int|float|numeric-string,ingredients?:list<array<string,mixed>>} $data */
    public function __construct(public array $data) {}
}
