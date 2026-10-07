<?php

namespace Domains\Bakery\Tests\Feature;

use Domains\User\Domain\Enums\UserRole;
use Domains\User\Domain\Enums\UserStatus;
use Domains\User\Domain\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\PendingCommand;

class AdminProvisioningTest extends TestCase
{
    use DatabaseTransactions;

    public function test_admin_is_created_with_hidden_password_and_duplicate_is_rejected(): void
    {
        $creation = $this->artisan('bakery:create-admin', ['email' => 'owner@example.test', '--name' => 'Владелец']);
        $this->assertInstanceOf(PendingCommand::class, $creation);
        $creation->expectsQuestion('Пароль администратора (не менее 10 символов)', 'StrongPassword123!')
            ->expectsQuestion('Повторите пароль', 'StrongPassword123!')
            ->assertExitCode(0)
            ->run();

        $admin = User::query()->where('email', 'owner@example.test')->firstOrFail();
        $this->assertSame('Владелец', $admin->name);
        $this->assertSame(UserRole::Admin, $admin->role);
        $this->assertSame(UserStatus::Verify, $admin->status);
        $this->assertNull($admin->bakery_id);
        $this->assertTrue(Hash::check('StrongPassword123!', $admin->password));

        $duplicate = $this->artisan('bakery:create-admin', ['email' => 'owner@example.test']);
        $this->assertInstanceOf(PendingCommand::class, $duplicate);
        $duplicate->assertExitCode(1)->run();
        $this->assertSame(1, User::query()->where('email', 'owner@example.test')->count());
    }
}
