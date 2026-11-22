<?php

namespace Domains\Order\Tests\Feature\FinancialConsistency;

use Domains\Order\Application\OrderingService;
use Domains\Order\Tests\Feature\Support\FinancialConsistencyCase;
use Domains\Price\Domain\Models\Price;
use Domains\Product\Domain\Models\Product;
use Domains\Store\Domain\Models\Store;
use Domains\User\Domain\Enums\UserRole;
use Domains\User\Domain\Enums\UserStatus;
use Domains\User\Domain\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class EmptyPeriodReportTest extends FinancialConsistencyCase
{
    public function test_empty_date_and_report_export_zeroes_without_leaking_orders(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-16 12:00:00', 'Europe/Moscow'));
        $store = Store::query()->create(['name' => 'Проверка пустой даты', 'address' => 'Адрес', 'is_active' => true]);
        $product = Product::query()->create(['name' => 'Пирог', 'sort_order' => 1, 'minimum_quantity' => 1, 'packaging_mode' => 'single', 'is_active' => true]);
        Price::query()->create(['bakery_id' => $store->id, 'product_id' => $product->id, 'kopecks' => 1250]);
        $admin = User::factory()->create(['role' => UserRole::Admin, 'status' => UserStatus::Verify]);
        $customer = User::factory()->create(['role' => UserRole::User, 'status' => UserStatus::Verify, 'bakery_id' => $store->id]);
        $service = app(OrderingService::class);
        $quote = $service->quote($store->id, [$product->id => 2]);
        $order = $service->place($store->id, (string) Str::uuid(), [$product->id => 2], $quote['hash'])['order'];
        $this->assertNotNull($order);
        $service->cancel($store->id, $order->id);

        $date = $quote['date'];
        foreach (['/admin/xlsx?date='.$date, '/admin/report/xlsx?from='.$date.'&to='.$date] as $url) {
            $this->actingAs($customer)->get($url)->assertForbidden();
        }
        $this->actingAs($admin)->get('/admin?date='.$date.'&tab=orders')->assertOk()->assertSee('На эту дату принятых заказов нет.');
        $this->actingAs($admin)->get('/admin/print?date='.$date)->assertOk()->assertDontSee('Проверка пустой даты');
        $this->actingAs($admin)->get('/admin/orders/'.$order->id.'/print')->assertNotFound();
        $this->actingAs($admin)->get('/admin/orders/'.$order->id.'/xlsx')->assertNotFound();
        $batch = $this->downloadCells('/admin/xlsx?date='.$date, 2);
        $this->assertContains('На выбранную дату принятых заказов нет.', $batch[1]);
        $this->assertSame('0', $this->cellForProduct($batch[2], 'Всего, шт.', 'B', 'A'));
        $this->assertSame('0.00', $this->cellForProduct($batch[2], 'Итого, ₽', 'B', 'A'));
        $this->actingAs($admin)->get('/admin/report?from='.$date.'&to='.$date)->assertOk()
            ->assertSee('Строк заказов: <strong>0</strong>', false)->assertSee('0,00 ₽');
        $report = $this->downloadCells('/admin/report/xlsx?from='.$date.'&to='.$date, 3);
        $this->assertArrayNotHasKey('A2', $report[1]);
        $this->assertArrayNotHasKey('A2', $report[2]);
    }
}
