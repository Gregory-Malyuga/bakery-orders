<?php

namespace Domains\Order\Tests\Feature\FinancialConsistency;

use Domains\Bakery\Application\OrderingService;
use Domains\Bakery\Domain\Models\Bakery;
use Domains\Bakery\Domain\Models\Price;
use Domains\Bakery\Domain\Models\Product;
use Domains\Order\Tests\Feature\Support\FinancialConsistencyCase;
use Domains\User\Domain\Enums\UserRole;
use Domains\User\Domain\Enums\UserStatus;
use Domains\User\Domain\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AcceptedOrderListTest extends FinancialConsistencyCase
{
    public function testAcceptedOrdersListShowsPieceCountNetTotalAndStoreComment(): void
    {
        $this->withoutMiddleware(PreventRequestForgery::class);
        Carbon::setTestNow(Carbon::parse('2026-10-16 12:00:00', 'Europe/Moscow'));
        $store = Bakery::query()->create(['name' => 'Точка с комментарием', 'address' => 'Адрес', 'is_active' => true]);
        $first = Product::query()->create(['name' => 'Пирог', 'sort_order' => 1, 'minimum_quantity' => 1, 'packaging_mode' => 'single', 'is_active' => true]);
        $second = Product::query()->create(['name' => 'Булочка', 'sort_order' => 2, 'minimum_quantity' => 1, 'packaging_mode' => 'single', 'is_active' => true]);
        Price::query()->create(['bakery_id' => $store->id, 'product_id' => $first->id, 'kopecks' => 1000]);
        Price::query()->create(['bakery_id' => $store->id, 'product_id' => $second->id, 'kopecks' => 2000]);
        $admin = User::factory()->create(['role' => UserRole::Admin, 'status' => UserStatus::Verify]);
        $service = app(OrderingService::class);
        $quote = $service->quote($store->id, [$first->id => 5, $second->id => 8]);
        $order = $service->place($store->id, (string) Str::uuid(), [$first->id => 5, $second->id => 8], $quote['hash'], "Первая строка\nВторая строка")['order'];
        $this->assertNotNull($order);
        $order->update(['admin_note' => 'Только для администратора']);
        $url = '/admin?date='.$quote['date'].'&tab=orders';

        $this->actingAs($admin)->get($url)->assertOk()
            ->assertSee('Изделий, шт.')->assertSee('Сумма после возвратов')
            ->assertSee('<td>13</td>', false)->assertSee('210,00 ₽')
            ->assertSee("Первая строка\nВторая строка")
            ->assertDontSee('Только для администратора');

        $this->actingAs($admin)->post('/admin/orders/'.$order->id.'/returns', [
            'product' => $first->id, 'quantity' => 2, 'reason' => 'Возврат',
        ])->assertRedirect();
        $this->actingAs($admin)->get($url)->assertOk()->assertSee('<td>13</td>', false)->assertSee('190,00 ₽');
        $returnId = DB::table('bakery_returns')->where('order_id', $order->id)->value('id');
        $this->assertIsInt($returnId);
        $this->actingAs($admin)->post('/admin/orders/'.$order->id.'/returns/'.$returnId.'/cancel', [
            'reason' => 'Ошибочный возврат',
        ])->assertRedirect();
        $this->actingAs($admin)->get($url)->assertOk()->assertSee('<td>13</td>', false)->assertSee('210,00 ₽');
    }
}
