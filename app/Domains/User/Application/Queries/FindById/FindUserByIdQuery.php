<?php

namespace Domains\User\Application\Queries\FindById;

use Shared\Bus\BaseQuery;

readonly class FindUserByIdQuery implements BaseQuery
{
    public function __construct(public int $id) {}
}
