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
use Illuminate\Support\Str;

class OrderFinancialViewsTest extends FinancialConsistencyCase
{
    public function testOrderPagesPrintoutsAndBothWorkbooksAgreeAfterAdjustmentReturnsAndPriceChange(): void
    {
        $this->withoutMiddleware(PreventRequestForgery::class);
        Carbon::setTestNow(Carbon::parse('2026-10-16 12:00:00', 'Europe/Moscow'));
        $first = Store::query()->create(['name' => 'Проверка А', 'address' => 'Адрес А', 'is_active' => true]);
        $second = Store::query()->create(['name' => 'Проверка Б', 'address' => 'Адрес Б', 'is_active' => true]);
        $pizza = Product::query()->create(['name' => 'Пицца', 'sort_order' => 1, 'minimum_quantity' => 1, 'packaging_mode' => 'single', 'is_active' => true]);
        $bun = Product::query()->create(['name' => 'Булочка', 'sort_order' => 2, 'minimum_quantity' => 1, 'packaging_mode' => 'single', 'is_active' => true]);
        foreach ([[$first, $pizza, 1234], [$first, $bun, 205], [$second, $pizza, 1530]] as [$store, $product, $kopecks]) {
            Price::query()->create(['bakery_id' => $store->id, 'product_id' => $product->id, 'kopecks' => $kopecks]);
        }
        $customer = User::factory()->create(['role' => UserRole::User, 'status' => UserStatus::Verify, 'bakery_id' => $first->id]);
        $admin = User::factory()->create(['role' => UserRole::Admin, 'status' => UserStatus::Verify]);
        $service = app(OrderingService::class);
        $firstQuote = $service->quote($first->id, [$pizza->id => 3, $bun->id => 2]);
        $this->assertSame(4112, $firstQuote['total']);
        $this->actingAs($customer)->post('/orders/preview', ['request_key' => (string) Str::uuid(), 'quantities' => [$pizza->id => 3, $bun->id => 2]])
            ->assertOk()->assertSee('41,12 ₽');
        $firstOrder = $service->place($first->id, (string) Str::uuid(), [$pizza->id => 3, $bun->id => 2], $firstQuote['hash'])['order'];
        $this->assertNotNull($firstOrder);
        $secondQuote = $service->quote($second->id, [$pizza->id => 2]);
        $secondOrder = $service->place($second->id, (string) Str::uuid(), [$pizza->id => 2], $secondQuote['hash'])['order'];
        $this->assertNotNull($secondOrder);
        $this->actingAs($admin)->post('/admin/orders/'.$firstOrder->id.'/edit', [
            'quantities' => [$pizza->id => 4, $bun->id => 2], 'reason' => 'Уточнение',
        ])->assertRedirect();
        $this->actingAs($admin)->post('/admin/orders/'.$firstOrder->id.'/returns', ['product' => $pizza->id, 'quantity' => 1])->assertRedirect();
        $this->actingAs($admin)->post('/admin/orders/'.$firstOrder->id.'/returns', ['product' => $bun->id, 'quantity' => 1])->assertRedirect();
        Price::query()->where('bakery_id', $first->id)->where('product_id', $pizza->id)->update(['kopecks' => 9999]);

        $date = $firstQuote['date'];
        $this->assertSame(5346, $firstOrder->fresh()?->total_kopecks);
        $this->actingAs($customer)->get('/orders/'.$firstOrder->id)->assertOk()->assertSee('53,46 ₽')->assertSee('12,34 ₽');
        $this->actingAs($admin)->get('/admin?date='.$date.'&tab=orders')->assertOk()->assertSee('39,07 ₽')->assertSee('30,60 ₽');
        $this->actingAs($admin)->get('/admin?date='.$date.'&tab=production')->assertOk()
            ->assertViewHas('totals', fn (array $totals): bool => $totals[$pizza->id] === 6 && $totals[$bun->id] === 2);
        $this->actingAs($admin)->get('/admin?date='.$date.'&tab=labels')->assertOk()->assertViewHas('labelTotal', 8);
        $this->actingAs($admin)->get('/admin?date='.$date.'&tab=prep')->assertOk()
            ->assertViewHas('production', fn (array $production): bool => $production['dough_grams'] === 816
                && is_array($production['ingredients']) && ($production['ingredients']['Помидоры'] ?? null) === 240);
        $this->actingAs($admin)->get('/admin/orders/'.$firstOrder->id)->assertOk()->assertSee('53,46 ₽')->assertSee('41,12 ₽');
        $this->actingAs($admin)->get('/admin/orders/'.$firstOrder->id.'/print')->assertOk()
            ->assertSee('53,46 ₽')->assertSee('14,39 ₽')->assertSee('39,07 ₽');
        $this->actingAs($admin)->get('/admin/print?date='.$date)->assertOk()
            ->assertSee('39,07 ₽')->assertSee('30,60 ₽');

        $single = $this->downloadCells('/admin/orders/'.$firstOrder->id.'/xlsx', 2);
        $this->assertSame('4', $this->cellForProduct($single[1], 'Пицца', 'C'));
        $this->assertSame('1', $this->cellForProduct($single[1], 'Пицца', 'D'));
        $this->assertSame('12.34', $this->cellForProduct($single[1], 'Пицца', 'E'));
        $this->assertSame('49.36', $this->cellForProduct($single[1], 'Пицца', 'F'));
        $this->assertSame('37.02', $this->cellForProduct($single[1], 'Пицца', 'G'));
        $this->assertSame('C7*E7', $this->formulaForProduct($single[1], 'Пицца', 'F'));
        $this->assertSame('(C7-D7)*E7', $this->formulaForProduct($single[1], 'Пицца', 'G'));
        $this->assertSame('53.46', $this->cellForProduct($single[1], 'Итого', 'F'));
        $this->assertSame('39.07', $this->cellForProduct($single[1], 'Итого', 'G'));

        $batch = $this->downloadCells('/admin/xlsx?date='.$date, 3);
        $this->assertSame('53.46', $this->cellForProduct($batch[1], 'Итого', 'F'));
        $this->assertSame('39.07', $this->cellForProduct($batch[1], 'Итого', 'G'));
        $this->assertSame('30.60', $this->cellForProduct($batch[2], 'Итого', 'E'));
        $this->assertNotContains('Возврат', $batch[2]);
        $this->assertSame('6', $this->cellForProduct($batch[3], 'Пицца', 'D', 'A'));
        $this->assertSame('2', $this->cellForProduct($batch[3], 'Булочка', 'D', 'A'));
        $this->assertSame('84.06', $this->cellForProduct($batch[3], 'Итого, ₽', 'D', 'A'));

        $period = '?from='.$date.'&to='.$date;
        $this->actingAs($admin)->get('/admin/report'.$period)->assertOk()
            ->assertSee('84,06 ₽')->assertSee('14,39 ₽')->assertSee('69,67 ₽');
        $report = $this->downloadCells('/admin/report/xlsx'.$period, 3);
        $this->assertSame('4', $this->reportValue($report[1], 'Проверка А', 'Пицца', 'D'));
        $this->assertSame('1', $this->reportValue($report[1], 'Проверка А', 'Пицца', 'E'));
        $this->assertSame('49.36', $this->reportValue($report[1], 'Проверка А', 'Пицца', 'G'));
        $this->assertSame('12.34', $this->reportValue($report[1], 'Проверка А', 'Пицца', 'H'));
        $this->assertSame('37.02', $this->reportValue($report[1], 'Проверка А', 'Пицца', 'I'));
        $this->assertSame('6', $this->cellForProduct($report[2], 'Пицца', 'B', 'A'));
        $this->assertSame('2', $this->cellForProduct($report[2], 'Булочка', 'B', 'A'));
        $this->assertSame('0.24', $this->cellForProduct($report[3], 'Помидоры', 'C'));
    }
}
