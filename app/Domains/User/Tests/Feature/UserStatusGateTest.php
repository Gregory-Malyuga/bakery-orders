<?php

namespace Domains\User\Tests\Feature;

use Domains\User\Domain\Enums\UserRole;
use Domains\User\Domain\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Laravel\Sanctum\Sanctum;

class UserStatusGateTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        config(['features.user_status_gate' => true]);
    }

    public function testVerifiedUserCanAccessUsersList(): void
    {
        Sanctum::actingAs(User::factory()->verified()->create(['role' => UserRole::Manager]));

        $this->getJson('/api/v1/users')->assertStatus(200);
    }

    public function testPendingUserIsBlocked(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => UserRole::Manager])); // pending по дефолту

        $this->getJson('/api/v1/users')->assertStatus(403)
            ->assertJsonPath('message', 'Аккаунт не верифицирован.');
    }

    public function testBannedUserIsBlocked(): void
    {
        Sanctum::actingAs(User::factory()->banned()->create(['role' => UserRole::Manager]));

        $this->getJson('/api/v1/users')->assertStatus(403);
    }

    public function testFlagOffPendingUserPasses(): void
    {
        config(['features.user_status_gate' => false]);
        Sanctum::actingAs(User::factory()->create(['role' => UserRole::Manager])); // pending

        $this->getJson('/api/v1/users')->assertStatus(200);
    }
}
