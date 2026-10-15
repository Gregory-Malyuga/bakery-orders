<?php

namespace Domains\Bakery\Tests\Feature;

use Domains\Bakery\Domain\Models\Bakery;
use Domains\User\Domain\Enums\UserRole;
use Domains\User\Domain\Enums\UserStatus;
use Domains\User\Domain\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;

class ImpersonationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_admin_can_enter_store_account_and_return(): void
    {
        $store = Bakery::query()->create(['name' => 'Магазин', 'address' => 'Адрес', 'is_active' => true]);
        $admin = User::factory()->create(['role' => UserRole::Admin, 'status' => UserStatus::Verify]);
        $account = User::factory()->create(['role' => UserRole::User, 'status' => UserStatus::Verify, 'bakery_id' => $store->id]);

        $this->actingAs($admin)->get('/admin/catalog?tab=accounts')
            ->assertOk()->assertSee('Войти как пользователь');

        $this->post('/admin/accounts/'.$account->id.'/impersonate')
            ->assertRedirect('/orders')->assertSessionHas('bakery.impersonating_admin_id', $admin->id);
        $this->assertAuthenticatedAs($account);
        $this->get('/orders')->assertOk()->assertSee('Режим пользователя: '.$account->name);
        $this->get('/admin')->assertForbidden();
        $this->post('/admin/accounts/'.$account->id.'/impersonate')->assertForbidden();

        $this->post('/impersonation/stop')->assertRedirect('/admin/catalog?tab=accounts')
            ->assertSessionMissing('bakery.impersonating_admin_id');
        $this->assertAuthenticatedAs($admin);
        $this->get('/admin')->assertOk();
    }

    public function test_only_admin_can_start_and_only_active_store_accounts_are_targets(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'status' => UserStatus::Verify]);
        $account = User::factory()->create(['role' => UserRole::User, 'status' => UserStatus::Verify]);
        $inactive = User::factory()->create(['role' => UserRole::User, 'status' => UserStatus::Banned]);

        $this->actingAs($account)->post('/admin/accounts/'.$admin->id.'/impersonate')->assertForbidden();
        $this->post('/impersonation/stop')->assertForbidden();
        $this->actingAs($admin)->post('/admin/accounts/'.$admin->id.'/impersonate')->assertNotFound();
        $this->post('/admin/accounts/'.$inactive->id.'/impersonate')->assertNotFound();
    }

    public function test_logout_clears_return_to_admin(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'status' => UserStatus::Verify]);
        $account = User::factory()->create(['role' => UserRole::User, 'status' => UserStatus::Verify]);

        $this->actingAs($admin)->post('/admin/accounts/'.$account->id.'/impersonate')->assertRedirect('/orders');
        $this->post('/logout')->assertRedirect('/login')->assertSessionMissing('bakery.impersonating_admin_id');
        $this->post('/impersonation/stop')->assertRedirect('/login');
        $this->assertGuest();
    }
}
