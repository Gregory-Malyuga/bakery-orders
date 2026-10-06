<?php

namespace Domains\User\Tests\Feature;

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

    public function test_verified_user_can_access_users_list(): void
    {
        Sanctum::actingAs(User::factory()->verified()->create());

        $this->getJson('/api/v1/users')->assertStatus(200);
    }

    public function test_pending_user_is_blocked(): void
    {
        Sanctum::actingAs(User::factory()->create()); // pending по дефолту

        $this->getJson('/api/v1/users')->assertStatus(403)
            ->assertJsonPath('message', 'Аккаунт не верифицирован.');
    }

    public function test_banned_user_is_blocked(): void
    {
        Sanctum::actingAs(User::factory()->banned()->create());

        $this->getJson('/api/v1/users')->assertStatus(403);
    }

    public function test_flag_off_pending_user_passes(): void
    {
        config(['features.user_status_gate' => false]);
        Sanctum::actingAs(User::factory()->create()); // pending

        $this->getJson('/api/v1/users')->assertStatus(200);
    }
}
