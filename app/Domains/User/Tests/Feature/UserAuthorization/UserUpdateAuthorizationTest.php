<?php

namespace Domains\User\Tests\Feature\UserAuthorization;

use Domains\User\Domain\Enums\UserRole;
use Domains\User\Domain\Models\User;
use Domains\User\Tests\Feature\Support\UserAuthorizationCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class UserUpdateAuthorizationTest extends UserAuthorizationCase
{
    use DatabaseTransactions;

    public function testManagerCannotUpdateUserToAdmin(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $target = User::factory()->create(['role' => UserRole::User]);

        $response = $this->actingAs($manager)
            ->putJson("/api/v1/users/{$target->id}", array_merge(
                $this->adminPayload(),
                ['email' => $target->email],
            ));

        $response->assertStatus(403);
    }

    public function testAdminCanUpdateUserToAdmin(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $target = User::factory()->create(['role' => UserRole::User]);

        $response = $this->actingAs($admin)
            ->putJson("/api/v1/users/{$target->id}", [
                'name' => $target->name,
                'email' => $target->email,
                'password' => 'secret1234',
                'role' => 'admin',
            ]);

        $response->assertStatus(200);
    }

    public function testManagerCannotUpdateAnotherManager(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $otherManager = User::factory()->create(['role' => UserRole::Manager]);

        $response = $this->actingAs($manager)
            ->putJson("/api/v1/users/{$otherManager->id}", [
                'name' => $otherManager->name,
                'email' => $otherManager->email,
                'role' => 'manager',
            ]);

        $response->assertStatus(403);
    }

    public function testAdminCanUpdateManager(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $manager = User::factory()->create(['role' => UserRole::Manager]);

        $response = $this->actingAs($admin)
            ->putJson("/api/v1/users/{$manager->id}", [
                'name' => $manager->name,
                'email' => $manager->email,
                'role' => 'manager',
            ]);

        $response->assertStatus(200);
    }
}
