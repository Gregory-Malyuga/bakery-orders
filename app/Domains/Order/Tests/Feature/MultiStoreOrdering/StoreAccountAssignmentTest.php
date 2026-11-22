<?php

namespace Domains\Order\Tests\Feature\MultiStoreOrdering;

use Domains\Order\Tests\Feature\Support\MultiStoreOrderingCase;
use Domains\Store\Domain\Models\Store;
use Domains\User\Domain\Enums\UserRole;
use Domains\User\Domain\Enums\UserStatus;
use Domains\User\Domain\Models\User;
use Illuminate\Support\Facades\DB;

class StoreAccountAssignmentTest extends MultiStoreOrderingCase
{
    public function testAdminCanAssignMultipleStoresToOneAccount(): void
    {
        $first = Store::query()->create(['name' => 'Балтийская 26', 'address' => '26', 'is_active' => true]);
        $second = Store::query()->create(['name' => 'Балтийская 35', 'address' => '35', 'is_active' => true]);
        $admin = User::factory()->create(['role' => UserRole::Admin, 'status' => UserStatus::Verify]);

        $this->actingAs($admin)->post('/admin/accounts', [
            'stores' => [$first->id, $second->id],
            'name' => 'Балтийская',
            'email' => 'baltiyskaya@example.test',
            'password' => 'StrongPassword123!',
            'is_active' => 1,
        ])->assertRedirect()->assertSessionHas('success');

        $account = User::query()->where('email', 'baltiyskaya@example.test')->firstOrFail();
        $this->assertSame([$first->id, $second->id], $account->accessibleStoreIds());
        $this->actingAs($admin)->get('/admin/catalog?tab=accounts')->assertOk()
            ->assertSee('Балтийская 26, Балтийская 35')
            ->assertSee('Изменить привязку')
            ->assertSee('type="checkbox" name="stores[]" value="'.$second->id.'" checked', false)
            ->assertSee('form="account-'.$account->id.'"', false);
        $this->actingAs($account)->get('/orders?store='.$second->id)->assertOk();

        $this->actingAs($admin)->post('/admin/accounts', [
            'id' => $account->id,
            'stores' => [$second->id],
            'name' => 'Балтийская',
            'email' => 'baltiyskaya@example.test',
            'is_active' => 1,
        ])->assertRedirect();
        $account->refresh();
        $this->assertSame([$second->id], $account->accessibleStoreIds());
        $this->actingAs($account)->get('/orders?store='.$first->id)->assertForbidden();
        $account->update(['bakery_id' => $first->id]);
        $this->assertSame([$second->id], $account->accessibleStoreIds());
        $this->actingAs($account)->get('/orders?store='.$first->id)->assertForbidden();
        $this->actingAs($account)->get('/orders?store='.$second->id)->assertOk();
    }

    public function testAccountsCanBeFilteredByAssignedStoreName(): void
    {
        $matchingStore = Store::query()->create(['name' => 'Редкая точка Северная', 'address' => 'Северная', 'is_active' => true]);
        $otherStore = Store::query()->create(['name' => 'Редкая точка Южная', 'address' => 'Южная', 'is_active' => true]);
        $matchingAccount = User::factory()->create(['role' => UserRole::User, 'status' => UserStatus::Verify, 'bakery_id' => $matchingStore->id]);
        $otherAccount = User::factory()->create(['role' => UserRole::User, 'status' => UserStatus::Verify, 'bakery_id' => $otherStore->id]);
        DB::table('bakery_account_stores')->insert([
            ['user_id' => $matchingAccount->id, 'bakery_id' => $matchingStore->id],
            ['user_id' => $otherAccount->id, 'bakery_id' => $otherStore->id],
        ]);
        $admin = User::factory()->create(['role' => UserRole::Admin, 'status' => UserStatus::Verify]);

        $this->actingAs($admin)->get('/admin/catalog?tab=accounts&search='.urlencode('сЕвЕрНаЯ'))
            ->assertOk()->assertSee('form="account-'.$matchingAccount->id.'"', false)
            ->assertDontSee('form="account-'.$otherAccount->id.'"', false);
    }

    public function testStoreCanBeUnassignedAndThenAssignedToAnotherAccount(): void
    {
        $store = Store::query()->create(['name' => 'Тестовый свободный магазин', 'address' => 'Адрес', 'is_active' => true]);
        $admin = User::factory()->create(['role' => UserRole::Admin, 'status' => UserStatus::Verify]);
        $first = User::factory()->create(['role' => UserRole::User, 'status' => UserStatus::Verify, 'bakery_id' => $store->id]);
        DB::table('bakery_account_stores')->insert(['user_id' => $first->id, 'bakery_id' => $store->id]);

        $this->actingAs($admin)->post('/admin/accounts', [
            'stores' => [$store->id], 'name' => 'Второй аккаунт', 'email' => 'second-store@example.test',
            'password' => 'StrongPassword123!', 'is_active' => 1,
        ])->assertSessionHasErrors('stores');
        $this->assertDatabaseMissing('users', ['email' => 'second-store@example.test']);

        $this->actingAs($admin)->post('/admin/accounts', [
            'id' => $first->id, 'name' => $first->name, 'email' => $first->email, 'is_active' => 1,
        ])->assertRedirect()->assertSessionHas('success');
        $first->refresh();
        $this->assertNull($first->bakery_id);
        $this->assertSame([], $first->accessibleStoreIds());
        $this->actingAs($first)->get('/orders')->assertOk()->assertSee('Нет назначенных магазинов');
        $this->actingAs($first)->get('/orders?store='.$store->id)->assertForbidden();
        $this->actingAs($admin)->get('/admin/catalog?tab=accounts')->assertOk()->assertSee('Нет магазинов');

        $this->actingAs($admin)->post('/admin/accounts', [
            'stores' => [$store->id], 'name' => 'Второй аккаунт', 'email' => 'second-store@example.test',
            'password' => 'StrongPassword123!', 'is_active' => 1,
        ])->assertRedirect()->assertSessionHas('success');
        $second = User::query()->where('email', 'second-store@example.test')->firstOrFail();
        $this->assertSame([$store->id], $second->accessibleStoreIds());
        $this->assertDatabaseHas('bakery_account_stores', ['user_id' => $second->id, 'bakery_id' => $store->id]);
        $this->actingAs($second)->get('/orders')->assertOk();
    }
}
