<?php

namespace Domains\User\Domain\Exceptions;

use Domains\User\Domain\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/** @extends ModelNotFoundException<User> */
class UserNotFoundException extends ModelNotFoundException
{
    public static function forId(int|string $id): self
    {
        return new self('Пользователь с таким идентификатором не найден');
    }
}
