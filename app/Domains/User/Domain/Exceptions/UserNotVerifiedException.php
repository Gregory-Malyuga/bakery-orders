<?php

namespace Domains\User\Domain\Exceptions;

use RuntimeException;

class UserNotVerifiedException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Аккаунт не верифицирован.');
    }
}
