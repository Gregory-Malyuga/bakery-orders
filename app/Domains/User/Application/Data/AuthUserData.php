<?php

namespace Domains\User\Application\Data;

use Domains\User\Domain\Enums\UserRole;
use Shared\Http\Data\BaseData;

class AuthUserData extends BaseData
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $email,
        public readonly UserRole $role,
    ) {}
}
