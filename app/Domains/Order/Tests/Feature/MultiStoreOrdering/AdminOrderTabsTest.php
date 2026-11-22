<?php

namespace Domains\Order\Tests\Feature\MultiStoreOrdering;

use Domains\Bakery\Domain\Models\Bakery;
use Domains\Order\Tests\Feature\Support\MultiStoreOrderingCase;
use Domains\User\Domain\Enums\UserRole;
use Domains\User\Domain\Enums\UserStatus;
use Domains\User\Domain\Models\User;

class AdminOrderTabsTest extends MultiStoreOrderingCase
{
    public function testAdminTabsShowOnlySelectedSectionAndKeepDate(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'status' => UserStatus::Verify]);
        $date = '2026-10-11';

        $this->actingAs($admin)->get('/admin?date='.$date.'&tab=orders')
            ->assertOk()->assertSee('Принятые заказы')->assertDontSee('Производственная сводка')
            ->assertSee('tab=production')->assertSee('value="'.$date.'"', false);
        $this->actingAs($admin)->get('/admin?date='.$date.'&tab=production')
            ->assertOk()->assertSee('Производственная сводка')->assertDontSee('Принятые заказы');
        $this->actingAs($admin)->get('/admin?date='.$date.'&tab=packaging')
            ->assertOk()->assertSee('Остаток, шт.')->assertDontSee('Заготовки по рецептурам');
        $this->actingAs($admin)->get('/admin?date='.$date.'&tab=prep')
            ->assertOk()->assertSee('Заготовки по рецептурам')->assertDontSee('Остаток, шт.');
        $this->actingAs($admin)->get('/admin/catalog?tab=accounts')
            ->assertOk()->assertSee('Аккаунты магазинов')->assertDontSee('Персональные цены');
        $this->actingAs($admin)->get('/admin/catalog?tab=prices')
            ->assertOk()->assertSee('Персональные цены')->assertDontSee('Аккаунты магазинов');

        for ($number = 1; $number <= 21; $number++) {
            Bakery::query()->create(['name' => sprintf('Магазин %02d', $number), 'address' => 'Тест', 'is_active' => true]);
        }
        $this->actingAs($admin)->get('/admin/catalog?tab=stores')
            ->assertOk()->assertSee('Магазин 01')->assertDontSee('Магазин 21')->assertSee('page=2');
        $this->actingAs($admin)->get('/admin/catalog?tab=stores&page=2')
            ->assertOk()->assertSee('Магазин 21')->assertDontSee('Магазин 01');
    }
}
