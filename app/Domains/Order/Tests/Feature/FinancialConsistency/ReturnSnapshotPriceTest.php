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
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ReturnSnapshotPriceTest extends FinancialConsistencyCase
{
    public function test_unordered_available_product_can_be_returned_at_snapshotted_price(): void
    {
        $this->withoutMiddleware(PreventRequestForgery::class);
        Carbon::setTestNow(Carbon::parse('2026-10-26 12:00:00', 'Europe/Moscow'));
        $store = Store::query()->create([
            'name' => 'Магазин возвратов',
            'address' => 'Адрес',
            'is_active' => true,
        ]);
        $sausage = Product::query()->create([
            'name' => 'Сосиска',
            'sort_order' => 1,
            'minimum_quantity' => 1,
            'packaging_mode' => 'single',
            'is_active' => true,
        ]);
        $cabbage = Product::query()->create([
            'name' => 'Капуста',
            'sort_order' => 2,
            'minimum_quantity' => 1,
            'packaging_mode' => 'single',
            'is_active' => true,
        ]);
        Price::query()->create([
            'bakery_id' => $store->id,
            'product_id' => $sausage->id,
            'kopecks' => 1000,
        ]);
        Price::query()->create([
            'bakery_id' => $store->id,
            'product_id' => $cabbage->id,
            'kopecks' => 250,
        ]);
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'status' => UserStatus::Verify,
        ]);
        $service = app(OrderingService::class);
        $quote = $service->quote($store->id, [$sausage->id => 1]);
        $order = $service->place(
            $store->id,
            (string) Str::uuid(),
            [$sausage->id => 1],
            $quote['hash'],
        )['order'];
        $this->assertNotNull($order);

        $this->actingAs($admin)
            ->get('/admin/orders/'.$order->id)
            ->assertOk()
            ->assertSee('Капуста');
        $this->actingAs($admin)
            ->get('/admin/orders/'.$order->id.'/print')
            ->assertOk()
            ->assertDontSee('Возврат, шт.');
        $before = $this->downloadCells('/admin/orders/'.$order->id.'/xlsx', 2);
        $this->assertNotContains('Возврат', $before[1]);
        $this->assertSame('10.00', $this->cellForProduct($before[1], 'Итого', 'E'));

        $this->actingAs($admin)
            ->post('/admin/orders/'.$order->id.'/returns', [
                'product' => $cabbage->id,
                'quantity' => 2,
                'reason' => 'Остаток прошлой поставки',
            ])
            ->assertRedirect();
        $this->assertDatabaseHas('bakery_returns', [
            'order_id' => $order->id,
            'product_id' => $cabbage->id,
            'product_name' => 'Капуста',
            'unit_kopecks' => 250,
            'quantity' => 2,
            'is_order_item' => false,
        ]);
        Price::query()
            ->where('bakery_id', $store->id)
            ->where('product_id', $cabbage->id)
            ->update(['kopecks' => 500]);
        $this->actingAs($admin)
            ->get('/admin/orders/'.$order->id.'/print')
            ->assertOk()
            ->assertSee('Возврат, шт.')
            ->assertSee('Капуста')
            ->assertSee('5,00 ₽');
        $invoice = $this->downloadCells('/admin/orders/'.$order->id.'/xlsx', 2);
        $this->assertSame('0', $this->cellForProduct($invoice[1], 'Капуста', 'C'));
        $this->assertSame('2', $this->cellForProduct($invoice[1], 'Капуста', 'D'));
        $this->assertSame('2.50', $this->cellForProduct($invoice[1], 'Капуста', 'E'));
        $this->assertSame('-5.00', $this->cellForProduct($invoice[1], 'Капуста', 'G'));
        $this->assertSame('5.00', $this->cellForProduct($invoice[1], 'Итого', 'G'));

        $date = $quote['date'];
        $this->actingAs($admin)
            ->get('/admin/report?from='.$date.'&to='.$date)
            ->assertOk()
            ->assertSee('5,00 ₽');
        $report = $this->downloadCells('/admin/report/xlsx?from='.$date.'&to='.$date, 3);
        $this->assertSame('0', $this->reportValue($report[1], 'Магазин возвратов', 'Капуста', 'D'));
        $this->assertSame('2', $this->reportValue($report[1], 'Магазин возвратов', 'Капуста', 'E'));
        $this->assertEqualsWithDelta(
            2.5,
            (float) $this->reportValue($report[1], 'Магазин возвратов', 'Капуста', 'F'),
            0.001,
        );
        $this->assertEqualsWithDelta(
            -5.0,
            (float) $this->reportValue($report[1], 'Магазин возвратов', 'Капуста', 'I'),
            0.001,
        );
        $this->assertSame(
            1,
            (int) DB::table('bakery_order_items')->where('order_id', $order->id)->sum('quantity'),
        );
    }
}
