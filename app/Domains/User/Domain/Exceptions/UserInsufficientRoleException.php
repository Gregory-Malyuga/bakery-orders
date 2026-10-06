<?php

namespace Domains\User\Domain\Exceptions;

use RuntimeException;

class UserInsufficientRoleException extends RuntimeException
{
    public static function cannotAssignRole(string $role): self
    {
        return new self("Недостаточно прав для назначения роли: {$role}");
    }

    public static function cannotDeleteAdmin(): self
    {
        return new self('Недостаточно прав для удаления администратора');
    }

    public static function cannotManageUser(): self
    {
        return new self('Недостаточно прав для управления этим пользователем');
    }
}
