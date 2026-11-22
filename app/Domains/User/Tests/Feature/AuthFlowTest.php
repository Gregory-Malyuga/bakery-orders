<?php

namespace Domains\User\Tests\Feature;

use Domains\User\Domain\Enums\UserRole;
use Domains\User\Domain\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Hash;

class AuthFlowTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    // ── Login ──────────────────────────────────────────────────────────

    public function testLoginReturns401ForWrongPassword(): void
    {
        User::factory()->create(['email' => 'user@test.com', 'password' => Hash::make('correct'), 'role' => UserRole::User]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'user@test.com',
            'password' => 'wrong',
        ]);

        $response->assertStatus(401);
    }

    public function testLoginReturns200WithTokenForUserRole(): void
    {
        User::factory()->create(['email' => 'user@test.com', 'password' => Hash::make('secret123'), 'role' => UserRole::User]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'user@test.com',
            'password' => 'secret123',
        ]);

        $response->assertStatus(200)->assertJsonStructure(['token', 'user']);
    }

    // ── Logout ────────────────────────────────────────────────────────────

    public function testLogoutInvalidatesToken(): void
    {
        $user = User::factory()->create(['role' => UserRole::Admin]);
        $token = $user->createToken('api')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/auth/logout')
            ->assertStatus(200);

        // Reset auth guard cache so the next request does a fresh token DB lookup
        $this->app['auth']->forgetGuards();

        $this->withToken($token)
            ->getJson('/api/v1/users')
            ->assertStatus(401);
    }

    // ── Register ──────────────────────────────────────────────────────────

    public function testRegistrationEndpointIsUnavailable(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Test User',
            'email' => 'new@test.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertNotFound();
        $this->assertDatabaseMissing('users', ['email' => 'new@test.com']);
    }
}
