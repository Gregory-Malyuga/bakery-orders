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

class GroupInvoiceTotalsTest extends FinancialConsistencyCase
{
    public function testGroupInvoiceAggregatesOrdersWithoutAddingThemToTheOrderMatrix(): void
    {
        $this->withoutMiddleware(PreventRequestForgery::class);
        Carbon::setTestNow(Carbon::parse('2026-10-16 12:00:00', 'Europe/Moscow'));
        $first = Bakery::query()->create(['name' => 'Первая точка', 'address' => 'Первый адрес', 'is_active' => true]);
        $missing = Bakery::query()->create(['name' => 'Нет заказа', 'address' => 'Второй адрес', 'is_active' => true]);
        $product = Product::query()->create(['name' => 'Пирог', 'sort_order' => 1, 'minimum_quantity' => 1, 'packaging_mode' => 'single', 'is_active' => true]);
        Price::query()->create(['bakery_id' => $first->id, 'product_id' => $product->id, 'kopecks' => 1000]);
        $admin = User::factory()->create(['role' => UserRole::Admin, 'status' => UserStatus::Verify]);
        $customer = User::factory()->create(['role' => UserRole::User, 'status' => UserStatus::Verify, 'bakery_id' => $first->id]);
        $service = app(OrderingService::class);
        $quote = $service->quote($first->id, [$product->id => 5]);
        $order = $service->place($first->id, (string) Str::uuid(), [$product->id => 5], $quote['hash'], 'Позвонить перед доставкой')['order'];
        $this->assertNotNull($order);
        $date = $quote['date'];

        $this->actingAs($customer)->post('/admin/groups', ['name' => 'Чужая группа'])->assertForbidden();
        $this->actingAs($admin)->post('/admin/groups', [
            'name' => 'Район', 'show_in_invoice' => 1, 'stores' => [$first->id, $missing->id],
        ])->assertRedirect();
        $groupId = DB::table('bakery_store_groups')->where('name', 'Район')->value('id');
        $this->assertIsInt($groupId);
        $this->actingAs($admin)->post('/admin/groups', [
            'name' => 'Только этикетки', 'exclude_from_labels' => 1, 'stores' => [$first->id],
        ])->assertRedirect();
        $this->actingAs($admin)->post('/admin/orders/'.$order->id.'/returns', [
            'product' => $product->id, 'quantity' => 1, 'reason' => 'Возврат',
        ])->assertRedirect();

        $this->actingAs($admin)->get('/admin?date='.$date.'&tab=orders')->assertOk()
            ->assertSee('Район')->assertDontSee('Только этикетки')
            ->assertSee('Есть заказы');
        $this->actingAs($admin)->get('/admin/groups')->assertOk()->assertSee('Только этикетки');
        $this->actingAs($admin)->get('/admin/groups/'.$groupId.'/print?date='.$date)->assertOk()
            ->assertSee('Сводная накладная группы')->assertSee('Нет заказа')
            ->assertSee('Позвонить перед доставкой')->assertSee('50,00 ₽')->assertSee('10,00 ₽')->assertSee('40,00 ₽')
            ->assertSee('Не прибавлять к общему итогу');
        $this->actingAs($admin)->get('/admin/print?date='.$date)->assertOk()
            ->assertSee('Накладная №'.$order->id)->assertSee('Сводная накладная группы');
        $groupSheet = $this->downloadCells('/admin/groups/'.$groupId.'/xlsx?date='.$date, 1)[1];
        $this->assertSame('5', $this->cellForProduct($groupSheet, 'Пирог', 'C'));
        $this->assertSame('1', $this->cellForProduct($groupSheet, 'Пирог', 'D'));
        $this->assertSame('40.00', $this->cellForProduct($groupSheet, 'Пирог', 'G'));
        $batch = $this->downloadCells('/admin/xlsx?date='.$date, 3);
        $this->assertSame('40.00', $this->cellForProduct($batch[2], 'Пирог', 'G'));
        $this->assertSame('5', $this->cellForProduct($batch[3], 'Пирог', 'B', 'A'));

        $second = Bakery::query()->create(['name' => 'Другая точка', 'address' => 'Третий адрес', 'is_active' => true]);
        Price::query()->create(['bakery_id' => $second->id, 'product_id' => $product->id, 'kopecks' => 1200]);
        $secondQuote = $service->quote($second->id, [$product->id => 2]);
        $secondOrder = $service->place($second->id, (string) Str::uuid(), [$product->id => 2], $secondQuote['hash'])['order'];
        $this->assertNotNull($secondOrder);
        $first->update(['name' => 'Переименованная точка']);
        $this->actingAs($admin)->post('/admin/groups', [
            'id' => $groupId, 'name' => 'Район', 'show_in_invoice' => 1,
            'stores' => [$first->id, $missing->id, $second->id],
        ])->assertRedirect();
        $this->actingAs($admin)->get('/admin/groups/'.$groupId.'/print?date='.$date)->assertOk()
            ->assertSee('Переименованная точка')->assertSee('74,00 ₽')->assertSee('64,00 ₽');
        $combined = $this->downloadCells('/admin/groups/'.$groupId.'/xlsx?date='.$date, 1)[1];
        $this->assertSame('10.00', $combined['E7']);
        $this->assertSame('12.00', $combined['E8']);
        $this->assertSame('74.00', $combined['F9']);
        $this->assertSame('64.00', $combined['G9']);
        $batch = $this->downloadCells('/admin/xlsx?date='.$date, 4);
        $this->assertSame('7', $this->cellForProduct($batch[4], 'Пирог', 'D', 'A'));

        $this->actingAs($admin)->post('/admin/groups', [
            'id' => $groupId, 'name' => 'Район', 'show_in_invoice' => 1, 'stores' => [$missing->id],
        ])->assertRedirect();
        $this->actingAs($admin)->get('/admin?date='.$date.'&tab=orders')->assertOk()
            ->assertSee('Район')->assertSee('Нет заказов');
        $this->actingAs($admin)->get('/admin/groups/'.$groupId.'/print?date='.$date)->assertNotFound();

        $this->actingAs($admin)->post('/admin/groups', [
            'id' => $groupId, 'name' => 'Новый район', 'stores' => [$missing->id],
        ])->assertRedirect();
        $this->actingAs($admin)->get('/admin?date='.$date.'&tab=orders')->assertOk()->assertDontSee('Сводные накладные групп');
        $this->actingAs($admin)->get('/admin/groups/'.$groupId.'/print?date='.$date)->assertNotFound();
    }
}
