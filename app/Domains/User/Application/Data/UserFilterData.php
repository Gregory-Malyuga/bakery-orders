<?php

namespace Domains\User\Application\Data;

use Domains\User\Domain\Enums\UserRole;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;

class UserFilterData extends Data
{
    public function __construct(
        public readonly ?string $search = null,
        public readonly ?string $name = null,
        public readonly ?string $email = null,
        public readonly ?string $role = null,
    ) {}

    /** @return array<string, list<mixed>> */
    public static function rules(): array
    {
        return [
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'role' => ['sometimes', 'nullable', Rule::in(UserRole::values())],
        ];
    }
}
