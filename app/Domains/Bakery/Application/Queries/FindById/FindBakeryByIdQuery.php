<?php

namespace Domains\Bakery\Application\Queries\FindById;

use Shared\Bus\BaseQuery;

readonly class FindBakeryByIdQuery implements BaseQuery
{
    public function __construct(public readonly int $id) {}
}
