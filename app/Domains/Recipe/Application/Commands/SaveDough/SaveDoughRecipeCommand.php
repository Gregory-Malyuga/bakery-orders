<?php

namespace Domains\Recipe\Application\Commands\SaveDough;

use Shared\Bus\BaseCommand;

readonly class SaveDoughRecipeCommand implements BaseCommand
{
    /** @param array{kind:string,batch_size:int|float|numeric-string,ingredients:list<array<string,mixed>>} $data */
    public function __construct(public array $data) {}
}
