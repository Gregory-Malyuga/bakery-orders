<?php

namespace Domains\User\Application\Data;

use Shared\Http\Data\BaseData;

class LoginData extends BaseData
{
    public function __construct(
        public readonly string $email,
        public readonly string $password,
    ) {}

    /** @return array<string, list<string>> */
    public static function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ];
    }
}
