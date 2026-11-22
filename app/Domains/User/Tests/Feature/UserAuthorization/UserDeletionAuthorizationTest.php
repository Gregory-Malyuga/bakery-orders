<?php

namespace Domains\User\Tests\Feature\UserAuthorization;

use Domains\User\Domain\Enums\UserRole;
use Domains\User\Domain\Models\User;
use Domains\User\Tests\Feature\Support\UserAuthorizationCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class UserDeletionAuthorizationTest extends UserAuthorizationCase
{
    use DatabaseTransactions;

    public function testManagerCannotDeleteAdmin(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $response = $this->actingAs($manager)
            ->deleteJson("/api/v1/users/{$admin->id}");

        $response->assertStatus(403);
    }

    public function testAdminCanDeleteAdmin(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $targetAdmin = User::factory()->create(['role' => UserRole::Admin]);

        $response = $this->actingAs($admin)
            ->deleteJson("/api/v1/users/{$targetAdmin->id}");

        $response->assertStatus(204);
    }

    public function testManagerCanDeleteUser(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $user = User::factory()->create(['role' => UserRole::User]);

        $response = $this->actingAs($manager)
            ->deleteJson("/api/v1/users/{$user->id}");

        $response->assertStatus(204);
    }

    public function testManagerCannotDeleteAnotherManager(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $otherManager = User::factory()->create(['role' => UserRole::Manager]);

        $response = $this->actingAs($manager)
            ->deleteJson("/api/v1/users/{$otherManager->id}");

        $response->assertStatus(403);
    }

    public function testAdminCanDeleteManager(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $manager = User::factory()->create(['role' => UserRole::Manager]);

        $response = $this->actingAs($admin)
            ->deleteJson("/api/v1/users/{$manager->id}");

        $response->assertStatus(204);
    }
}
