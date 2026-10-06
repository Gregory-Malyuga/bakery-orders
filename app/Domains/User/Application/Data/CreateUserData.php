<?php

namespace Domains\User\Application\Data;

use Domains\User\Domain\Enums\UserRole;
use Shared\Http\Data\BaseData;

class CreateUserData extends BaseData
{
    public function __construct(
        public readonly string $name,
        public readonly string $email,
        public readonly string $password,
        public readonly UserRole $role,
    ) {}

    /** @return array<string, list<string>> */
    public static function rules(): array
    {
        $roles = implode(',', UserRole::values());

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', 'string', "in:{$roles}"],
        ];
    }
}
