<?php

namespace Domains\Product\Application\Repositories;

use Illuminate\Support\Collection;

interface ProductOptionsReadRepositoryInterface
{
    /** @return Collection<int, \stdClass> */
    public function productOptions(): Collection;
}
