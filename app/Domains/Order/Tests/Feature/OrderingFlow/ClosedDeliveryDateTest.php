<?php

namespace Domains\Order\Tests\Feature\OrderingFlow;

use Carbon\Carbon;
use Domains\Order\Application\OrderingService;
use Domains\Order\Tests\Feature\Support\OrderingFlowCase;
use Domains\User\Domain\Enums\UserRole;
use Domains\User\Domain\Enums\UserStatus;
use Domains\User\Domain\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Str;

class ClosedDeliveryDateTest extends OrderingFlowCase
{
    public function test_closed_consecutive_delivery_dates_never_move_an_order_forward(): void
    {
        $this->withoutMiddleware(PreventRequestForgery::class);
        Carbon::setTestNow(Carbon::parse('2026-11-01 12:00:00', 'Europe/Moscow'));
        [$store, $user, $product] = $this->shop('Закрытые даты', 3000);
        $admin = User::factory()->create(['role' => UserRole::Admin, 'status' => UserStatus::Verify]);

        foreach (['2026-11-02', '2026-11-03'] as $date) {
            $this->actingAs($admin)->post('/admin/closed-dates', ['delivery_date' => $date, 'reason' => 'Выходной'])->assertRedirect();
        }

        $this->actingAs($user)->get('/orders')->assertOk()
            ->assertSee('Поставка закрыта: Выходной')
            ->assertSee('Заказы принимаются с 03.11.2026, поставка 04.11.2026')
            ->assertDontSee('id="order-form"', false);
        $input = ['store' => $store->id, 'request_key' => (string) Str::uuid(), 'quantities' => [$product->id => 1]];
        $this->actingAs($user)->post('/orders/preview', $input)->assertStatus(422);
        $this->actingAs($user)->post('/orders/edit-preview', $input)->assertStatus(422);
        $this->actingAs($user)->post('/orders', $input + ['quote_hash' => str_repeat('a', 64)])->assertSessionHasErrors('order');

        Carbon::setTestNow(Carbon::parse('2026-11-02 12:00:00', 'Europe/Moscow'));
        $this->actingAs($user)->get('/orders')->assertOk()->assertSee('Заказы принимаются с 03.11.2026, поставка 04.11.2026');

        Carbon::setTestNow(Carbon::parse('2026-11-03 12:00:00', 'Europe/Moscow'));
        $this->actingAs($user)->get('/orders')->assertOk()->assertSee('id="order-form"', false);
        $quote = app(OrderingService::class)->quote($store->id, [$product->id => 1]);
        $this->assertSame('2026-11-04', $quote['date']);
        $this->assertNotNull(app(OrderingService::class)->place($store->id, (string) Str::uuid(), [$product->id => 1], $quote['hash'])['order']);
    }

    public function test_closing_a_date_with_accepted_orders_requires_explicit_confirmation(): void
    {
        $this->withoutMiddleware(PreventRequestForgery::class);
        Carbon::setTestNow(Carbon::parse('2026-11-01 12:00:00', 'Europe/Moscow'));
        [$store, $user, $product] = $this->shop('Существующий заказ', 3000);
        $service = app(OrderingService::class);
        $quote = $service->quote($store->id, [$product->id => 1]);
        $order = $service->place($store->id, (string) Str::uuid(), [$product->id => 1], $quote['hash'])['order'];
        $this->assertNotNull($order);
        $admin = User::factory()->create(['role' => UserRole::Admin, 'status' => UserStatus::Verify]);
        $input = ['delivery_date' => '2026-11-02', 'reason' => 'Ремонт печи'];

        $this->actingAs($admin)->post('/admin/closed-dates', $input)->assertSessionHasErrors('delivery_date');
        $this->assertDatabaseMissing('bakery_closed_delivery_dates', ['delivery_date' => '2026-11-02']);
        $this->actingAs($admin)->post('/admin/closed-dates', $input + ['keep_existing_orders' => 1])->assertRedirect();
        $this->actingAs($admin)->get('/admin/closed-dates')->assertOk()->assertSee('Ремонт печи');
        $this->assertDatabaseHas('bakery_orders', ['id' => $order->id, 'status' => 'accepted']);
        $this->actingAs($user)->get('/orders/'.$order->id)->assertOk();
        $service->cancel($store->id, $order->id);
        $this->assertDatabaseHas('bakery_orders', ['id' => $order->id, 'status' => 'cancelled']);
        $this->actingAs($admin)->post('/admin/closed-dates/2026-11-02/open')->assertRedirect();
        $this->assertDatabaseMissing('bakery_closed_delivery_dates', ['delivery_date' => '2026-11-02']);
    }

    public function test_invalid_closed_date_has_clear_russian_error(): void
    {
        $this->withoutMiddleware(PreventRequestForgery::class);
        $admin = User::factory()->create(['role' => UserRole::Admin, 'status' => UserStatus::Verify]);
        $this->actingAs($admin)->post('/admin/closed-dates', ['delivery_date' => '12.11.2026', 'reason' => 'Выходной'])
            ->assertSessionHasErrors(['delivery_date' => 'Укажите корректную дату поставки.']);
    }
}
