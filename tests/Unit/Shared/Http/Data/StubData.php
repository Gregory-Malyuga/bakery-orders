<?php

namespace Tests\Unit\Shared\Http\Data;

use Shared\Http\Data\BaseData;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Required;

class StubData extends BaseData
{
    public function __construct(
        #[Required, Max(255)]
        public readonly string $name,
        public readonly int $value,
    ) {}
}
