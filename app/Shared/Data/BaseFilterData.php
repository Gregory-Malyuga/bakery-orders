<?php

namespace Shared\Data;

use Spatie\LaravelData\Data;

abstract class BaseFilterData extends Data
{
    public ?string $search = null;
}
