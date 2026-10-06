<?php

namespace Domains\User\Application\Queries\FindByCredentials;

use Shared\Bus\BaseQuery;

readonly class FindUserByCredentialsQuery implements BaseQuery
{
    public function __construct(
        public string $email,
        public string $password,
    ) {}
}
