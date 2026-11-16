<?php

namespace Domains\Recipe\Application\Commands\Reset;

use Shared\Bus\BaseCommand;

readonly class ResetRecipeCommand implements BaseCommand
{
    /** @param array{kind:string,product?:int|string} $data */
    public function __construct(public array $data) {}
}
