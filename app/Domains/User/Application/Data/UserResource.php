<?php

namespace Domains\User\Application\Data;

use Domains\User\Domain\Enums\UserRole;
use Shared\Http\Data\BaseData;

class UserResource extends BaseData
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $email,
        public readonly UserRole $role,
        public readonly ?string $created_at = null,
        public readonly ?string $updated_at = null,
    ) {}
}
