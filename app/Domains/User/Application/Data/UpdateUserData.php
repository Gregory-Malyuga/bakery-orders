<?php

namespace Domains\User\Application\Data;

use Domains\User\Domain\Enums\UserRole;
use Shared\Http\Data\BaseData;

class UpdateUserData extends BaseData
{
    public function __construct(
        public readonly string $name,
        public readonly string $email,
        public readonly UserRole $role,
        public readonly ?string $password = null,
    ) {}

    /** @return array<string, list<string>> */
    public static function rules(): array
    {
        $roles = implode(',', UserRole::values());

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['sometimes', 'nullable', 'string', 'min:8'],
            'role' => ['required', 'string', "in:{$roles}"],
        ];
    }
}
