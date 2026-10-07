<?php

namespace Domains\Bakery\Application\Data;

use Spatie\LaravelData\Data;

class BakeryFilterData extends Data
{
    public function __construct(
        public readonly ?string $name = null,
    ) {}
}
