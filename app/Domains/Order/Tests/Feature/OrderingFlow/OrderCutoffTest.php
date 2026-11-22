<?php

namespace Domains\Order\Tests\Feature\OrderingFlow;

use Carbon\Carbon;
use Domains\Order\Application\OrderingService;
use Domains\Order\Application\Repositories\OrderRepositoryInterface;
use Domains\Order\Tests\Feature\Support\OrderingFlowCase;
use Domains\User\Domain\Enums\UserRole;
use Domains\User\Domain\Enums\UserStatus;
use Domains\User\Domain\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Str;

class OrderCutoffTest extends OrderingFlowCase
{
    public function test_cutoff_and_cancellation_remove_order_from_summary_and_print(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-06 18:29:59', 'Europe/Moscow'));
        [$store, $user, $product] = $this->shop('Третий', 3200);
        $service = app(OrderingService::class);
        $quote = $service->quote($store->id, [$product->id => 1]);
        $order = $service->place($store->id, (string) Str::uuid(), [$product->id => 1], $quote['hash'])['order'];
        $this->assertNotNull($order);
        $service->cancel($store->id, $order->id);
        $this->assertCount(0, app(OrderRepositoryInterface::class)->orders($quote['date']));
        $admin = User::factory()->create(['role' => UserRole::Admin, 'status' => UserStatus::Verify]);
        $this->actingAs($admin)->get('/admin/print?date='.$quote['date'])->assertOk()->assertDontSee('Третий');
        Carbon::setTestNow(Carbon::parse('2026-10-06 18:30:00', 'Europe/Moscow'));
        $this->assertFalse($service->isOpen());
        $this->actingAs($user)->get('/orders')->assertOk()->assertSee('Приём закрыт');
    }

    public function test_client_clock_cannot_bypass_server_cutoff(): void
    {
        $this->withoutMiddleware(PreventRequestForgery::class);
        Carbon::setTestNow(Carbon::parse('2026-10-06 18:29:59', 'Europe/Moscow'));
        [$store, $user, $product] = $this->shop('Шестой', 3000);
        $service = app(OrderingService::class);
        $quote = $service->quote($store->id, [$product->id => 1]);

        Carbon::setTestNow(Carbon::parse('2026-10-06 18:30:00', 'Europe/Moscow'));
        $this->actingAs($user)->withHeaders(['Date' => 'Tue, 06 Oct 2026 12:00:00 GMT', 'X-Client-Time' => '12:00'])->post('/orders', [
            'request_key' => (string) Str::uuid(),
            'quantities' => [$product->id => 1],
            'quote_hash' => $quote['hash'],
            'delivery_date' => $quote['date'],
            'client_time' => '12:00',
        ])->assertSessionHasErrors('order');
        $this->assertCount(0, app(OrderRepositoryInterface::class)->orders($quote['date']));
    }
}
