<?php

namespace Domains\User\Tests\Feature\UserAuthorization;

use Domains\User\Domain\Enums\UserRole;
use Domains\User\Domain\Models\User;
use Domains\User\Tests\Feature\Support\UserAuthorizationCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class UserCreationAuthorizationTest extends UserAuthorizationCase
{
    use DatabaseTransactions;

    public function testManagerCannotCreateAdmin(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);

        $response = $this->actingAs($manager)
            ->postJson('/api/v1/users', $this->adminPayload());

        $response->assertStatus(403);
    }

    public function testAdminCanCreateAdmin(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $response = $this->actingAs($admin)
            ->postJson('/api/v1/users', $this->adminPayload());

        $response->assertStatus(201);
    }

    public function testManagerCanCreateUserRole(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);

        $response = $this->actingAs($manager)
            ->postJson('/api/v1/users', $this->userPayload());

        $response->assertStatus(201);
    }
}
