<?php

namespace Domains\User\Application\Data;

use Shared\Http\Data\BaseData;

class AuthTokenData extends BaseData
{
    public function __construct(
        public readonly string $token,
        public readonly AuthUserData $user,
    ) {}
}
