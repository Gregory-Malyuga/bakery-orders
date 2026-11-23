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

class ReturnCancellationViewsTest extends FinancialConsistencyCase
{
    public function test_cancelling_one_duplicate_return_preserves_history_and_updates_every_financial_view(): void
    {
        $this->withoutMiddleware(PreventRequestForgery::class);
        Carbon::setTestNow(Carbon::parse('2026-10-16 12:00:00', 'Europe/Moscow'));
        $store = Store::query()->create([
            'name' => 'Точка возврата',
            'address' => 'Адрес',
            'is_active' => true,
        ]);
        $product = Product::query()->create([
            'name' => 'Пирог',
            'sort_order' => 1,
            'minimum_quantity' => 1,
            'packaging_mode' => 'single',
            'is_active' => true,
        ]);
        Price::query()->create([
            'bakery_id' => $store->id,
            'product_id' => $product->id,
            'kopecks' => 1000,
        ]);
        $admin = User::factory()->create([
            'name' => 'Администратор возвратов',
            'role' => UserRole::Admin,
            'status' => UserStatus::Verify,
        ]);
        $customer = User::factory()->create([
            'role' => UserRole::User,
            'status' => UserStatus::Verify,
            'bakery_id' => $store->id,
        ]);
        $service = app(OrderingService::class);
        $quote = $service->quote($store->id, [$product->id => 5]);
        $order = $service->place(
            $store->id,
            (string) Str::uuid(),
            [$product->id => 5],
            $quote['hash'],
        )['order'];
        $this->assertNotNull($order);
        $url = '/admin/orders/'.$order->id.'/returns';
        $this->actingAs($admin)
            ->post($url, ['product' => $product->id, 'quantity' => 1])
            ->assertRedirect();
        $this->actingAs($admin)
            ->post($url, ['product' => $product->id, 'quantity' => 1])
            ->assertRedirect();
        $returnIds = array_map(static function (mixed $id): int {
            if (! is_int($id) && ! is_string($id)) {
                throw new \UnexpectedValueException('Некорректный ID возврата.');
            }

            return (int) $id;
        }, DB::table('bakery_returns')
            ->where('order_id', $order->id)
            ->orderBy('id')
            ->pluck('id')
            ->all());
        $this->assertCount(2, $returnIds);
        $cancelUrl = $url.'/'.$returnIds[0].'/cancel';

        $this->actingAs($customer)
            ->post($cancelUrl, ['reason' => 'Дубль'], ['Accept' => 'application/json'])
            ->assertForbidden();
        $this->actingAs($admin)
            ->post($cancelUrl, ['reason' => '   '])
            ->assertSessionHasErrors('reason');
        $this->actingAs($admin)
            ->post($cancelUrl, ['reason' => 'Дублированная запись'])
            ->assertRedirect();
        $this->actingAs($admin)
            ->post($cancelUrl, ['reason' => 'Повтор'], ['Accept' => 'application/json'])
            ->assertStatus(422);
        $this->assertDatabaseCount('bakery_returns', 2);
        $this->assertDatabaseHas('bakery_returns', [
            'id' => $returnIds[0],
            'status' => 'cancelled',
            'cancelled_by_user_id' => $admin->id,
            'cancellation_reason' => 'Дублированная запись',
        ]);
        $this->assertDatabaseHas('bakery_returns', [
            'id' => $returnIds[1],
            'status' => 'active',
            'cancelled_at' => null,
        ]);
        $this->actingAs($admin)
            ->get('/admin/orders/'.$order->id)
            ->assertOk()
            ->assertSee('Дублированная запись')
            ->assertSee('Администратор возвратов')
            ->assertSee('40,00 ₽');
        $date = $quote['date'];
        $this->actingAs($admin)
            ->get('/admin?date='.$date.'&tab=orders')
            ->assertOk()
            ->assertSee('40,00 ₽');
        $this->actingAs($admin)
            ->get('/admin/orders/'.$order->id.'/print')
            ->assertOk()
            ->assertSee('40,00 ₽')
            ->assertSee('10,00 ₽');
        $this->actingAs($admin)
            ->get('/admin/print?date='.$date)
            ->assertOk()
            ->assertSee('40,00 ₽');
        $single = $this->downloadCells('/admin/orders/'.$order->id.'/xlsx', 2);
        $this->assertSame('1', $this->cellForProduct($single[1], 'Пирог', 'D'));
        $this->assertSame('40.00', $this->cellForProduct($single[1], 'Итого', 'G'));
        $batch = $this->downloadCells('/admin/xlsx?date='.$date, 2);
        $this->assertSame('40.00', $this->cellForProduct($batch[1], 'Итого', 'G'));
        $this->actingAs($admin)
            ->get('/admin/report?from='.$date.'&to='.$date)
            ->assertOk()
            ->assertSee('40,00 ₽');
        $report = $this->downloadCells('/admin/report/xlsx?from='.$date.'&to='.$date, 3);
        $this->assertSame('1', $this->reportValue($report[1], 'Точка возврата', 'Пирог', 'E'));
        $this->assertEqualsWithDelta(
            40.0,
            (float) $this->reportValue($report[1], 'Точка возврата', 'Пирог', 'I'),
            0.001,
        );

        $service->cancel($store->id, $order->id);
        $this->actingAs($admin)
            ->post(
                $url,
                ['product' => $product->id, 'quantity' => 1],
                ['Accept' => 'application/json'],
            )
            ->assertStatus(422);
    }
}
