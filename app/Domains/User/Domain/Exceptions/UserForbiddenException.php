<?php

namespace Domains\User\Domain\Exceptions;

use RuntimeException;

class UserForbiddenException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Доступ запрещён.');
    }
}
