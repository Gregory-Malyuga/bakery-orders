<?php

namespace Domains\User\Tests\Feature;

use Domains\User\Domain\Enums\UserRole;
use Domains\User\Domain\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;

class UserRequestValidationTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($admin, 'sanctum');
    }

    // ── ListUsersRequest ──────────────────────────────────────────────────

    public function test_index_rejects_invalid_sort_field(): void
    {
        $this->getJson('/api/v1/users?sort=password')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['sort']);
    }

    public function test_index_rejects_invalid_sort_direction(): void
    {
        $this->getJson('/api/v1/users?direction=random')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['direction']);
    }

    public function test_index_accepts_valid_sort_fields(): void
    {
        foreach (['id', 'name', 'email', 'created_at'] as $field) {
            $this->getJson("/api/v1/users?sort={$field}&direction=asc")
                ->assertStatus(200);
        }
    }

    // ── StoreUserRequest ──────────────────────────────────────────────────

    public function test_store_rejects_missing_required_fields(): void
    {
        $this->postJson('/api/v1/users', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'password']);
    }

    public function test_store_rejects_invalid_email(): void
    {
        $this->postJson('/api/v1/users', [
            'name' => 'Test',
            'email' => 'not-an-email',
            'password' => 'secret1234',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_store_rejects_duplicate_email(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->postJson('/api/v1/users', [
            'name' => 'Test',
            'email' => 'taken@example.com',
            'password' => 'secret1234',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    // ── UpdateUserRequest ─────────────────────────────────────────────────

    public function test_update_rejects_duplicate_email(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);
        $user = User::factory()->create(['email' => 'mine@example.com', 'role' => UserRole::User]);

        $this->putJson("/api/v1/users/{$user->id}", [
            'name' => $user->name,
            'email' => 'taken@example.com',
            'role' => 'user',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_update_allows_keeping_own_email(): void
    {
        $user = User::factory()->create(['email' => 'own@example.com', 'role' => UserRole::User]);

        $this->putJson("/api/v1/users/{$user->id}", [
            'name' => $user->name,
            'email' => 'own@example.com',
            'role' => 'user',
        ])->assertStatus(200);
    }

    public function test_update_rejects_missing_required_fields(): void
    {
        $user = User::factory()->create();

        $this->putJson("/api/v1/users/{$user->id}", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'role']);
    }
}
