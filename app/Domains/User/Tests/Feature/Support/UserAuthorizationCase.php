<?php

namespace Domains\User\Tests\Feature\Support;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;

abstract class UserAuthorizationCase extends TestCase
{
    use DatabaseTransactions;

    /** @return array<string, mixed> */
    protected function adminPayload(): array
    {
        return [
            'name' => 'New Admin',
            'email' => 'newadmin@example.com',
            'password' => 'secret1234',
            'role' => 'admin',
        ];
    }

    /** @return array<string, mixed> */
    protected function userPayload(): array
    {
        return [
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'password' => 'secret1234',
            'role' => 'user',
        ];
    }
}
