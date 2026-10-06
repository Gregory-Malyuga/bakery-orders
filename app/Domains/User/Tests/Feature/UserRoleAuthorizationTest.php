<?php

namespace Domains\User\Tests\Feature;

use Domains\User\Domain\Enums\UserRole;
use Domains\User\Domain\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;

class UserRoleAuthorizationTest extends TestCase
{
    use DatabaseTransactions;

    /** @return array<string, mixed> */
    private function adminPayload(): array
    {
        return [
            'name' => 'New Admin',
            'email' => 'newadmin@example.com',
            'password' => 'secret1234',
            'role' => 'admin',
        ];
    }

    /** @return array<string, mixed> */
    private function userPayload(): array
    {
        return [
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'password' => 'secret1234',
            'role' => 'user',
        ];
    }

    public function test_manager_cannot_create_admin(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);

        $response = $this->actingAs($manager)
            ->postJson('/api/v1/users', $this->adminPayload());

        $response->assertStatus(403);
    }

    public function test_manager_cannot_update_user_to_admin(): void
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

    public function test_admin_can_create_admin(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $response = $this->actingAs($admin)
            ->postJson('/api/v1/users', $this->adminPayload());

        $response->assertStatus(201);
    }

    public function test_admin_can_update_user_to_admin(): void
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

    public function test_manager_can_create_user_role(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);

        $response = $this->actingAs($manager)
            ->postJson('/api/v1/users', $this->userPayload());

        $response->assertStatus(201);
    }

    public function test_manager_cannot_delete_admin(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $response = $this->actingAs($manager)
            ->deleteJson("/api/v1/users/{$admin->id}");

        $response->assertStatus(403);
    }

    public function test_admin_can_delete_admin(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $targetAdmin = User::factory()->create(['role' => UserRole::Admin]);

        $response = $this->actingAs($admin)
            ->deleteJson("/api/v1/users/{$targetAdmin->id}");

        $response->assertStatus(204);
    }

    public function test_manager_can_delete_user(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $user = User::factory()->create(['role' => UserRole::User]);

        $response = $this->actingAs($manager)
            ->deleteJson("/api/v1/users/{$user->id}");

        $response->assertStatus(204);
    }

    public function test_manager_cannot_delete_another_manager(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $otherManager = User::factory()->create(['role' => UserRole::Manager]);

        $response = $this->actingAs($manager)
            ->deleteJson("/api/v1/users/{$otherManager->id}");

        $response->assertStatus(403);
    }

    public function test_manager_cannot_update_another_manager(): void
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

    public function test_admin_can_delete_manager(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $manager = User::factory()->create(['role' => UserRole::Manager]);

        $response = $this->actingAs($admin)
            ->deleteJson("/api/v1/users/{$manager->id}");

        $response->assertStatus(204);
    }

    public function test_admin_can_update_manager(): void
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
