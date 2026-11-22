<?php

namespace Domains\Order\Tests\Feature\OrderingFlow;

use Carbon\Carbon;
use Domains\Analytics\Application\AnalyticsService;
use Domains\Order\Application\OrderingService;
use Domains\Order\Tests\Feature\Support\OrderingFlowCase;
use Domains\User\Domain\Enums\UserRole;
use Domains\User\Domain\Enums\UserStatus;
use Domains\User\Domain\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderAnalyticsTest extends OrderingFlowCase
{
    public function testAnalyticsUsesDeliveryDateActiveOrdersAndActiveReturns(): void
    {
        $this->withoutMiddleware(PreventRequestForgery::class);
        Carbon::setTestNow(Carbon::parse('2026-10-06 12:00:00', 'Europe/Moscow'));
        [$store, , $product] = $this->shop('Аналитика первая', 5000);
        $this->shop('Аналитика без заказа', 5000);
        $service = app(OrderingService::class);
        $quote = $service->quote($store->id, [$product->id => 3]);
        $order = $service->place($store->id, (string) Str::uuid(), [$product->id => 3], $quote['hash'])['order'];
        $this->assertNotNull($order);
        $admin = User::factory()->create(['role' => UserRole::Admin, 'status' => UserStatus::Verify]);
        $this->actingAs($admin)->post('/admin/orders/'.$order->id.'/returns', ['product' => $product->id, 'quantity' => 1, 'reason' => 'Брак'])->assertRedirect();

        $result = app(AnalyticsService::class)->build('2026-10-07', '2026-10-07');
        $this->assertSame(1, $result['summary']['orders']);
        $this->assertSame(3, $result['summary']['pieces']);
        $this->assertSame(15000, $result['summary']['gross']);
        $this->assertSame(5000, $result['summary']['returns']);
        $this->assertSame(10000, $result['summary']['net']);
        $this->assertEqualsWithDelta(33.333, $result['summary']['return_share'], 0.01);
        $this->assertContains('Аналитика без заказа', $result['missing_stores']);
        $this->actingAs($admin)->get('/admin/analytics?from=2026-10-07&to=2026-10-07')->assertOk()->assertSee('100,00 ₽');
        $this->actingAs($admin)->get('/admin/analytics/csv?from=2026-10-07&to=2026-10-07')->assertOk()->assertDownload('analitika-2026-10-07-2026-10-07.csv');

        $this->assertSame(0, app(AnalyticsService::class)->build('2026-10-06', '2026-10-06')['summary']['orders']);
        $this->assertSame(1, app(AnalyticsService::class)->build('2026-10-07', '2026-10-07', $store->id, $product->id)['summary']['orders']);
        DB::table('bakery_returns')->where('order_id', $order->id)->update(['status' => 'cancelled']);
        $this->assertSame(15000, app(AnalyticsService::class)->build('2026-10-07', '2026-10-07')['summary']['net']);
        $service->cancel($store->id, $order->id);
        $this->assertSame(0, app(AnalyticsService::class)->build('2026-10-07', '2026-10-07')['summary']['orders']);
    }
}
