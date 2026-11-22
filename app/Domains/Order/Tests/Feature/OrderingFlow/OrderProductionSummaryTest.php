<?php

namespace Domains\Order\Tests\Feature\OrderingFlow;

use Carbon\Carbon;
use Domains\Order\Application\OrderingService;
use Domains\Order\Tests\Feature\Support\OrderingFlowCase;
use Domains\Price\Domain\Models\Price;
use Domains\Product\Domain\Models\Product;
use Domains\User\Domain\Enums\UserRole;
use Domains\User\Domain\Enums\UserStatus;
use Domains\User\Domain\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Str;

class OrderProductionSummaryTest extends OrderingFlowCase
{
    public function testLabelsCountPackagesAndProductionShowsTotalPieces(): void
    {
        $this->withoutMiddleware(PreventRequestForgery::class);
        Carbon::setTestNow(Carbon::parse('2026-10-06 12:00:00', 'Europe/Moscow'));
        [$store, $user, $product] = $this->shop('Фасовка для наклеек', 3000);
        $product->update(['packaging_mode' => 'mixed', 'minimum_quantity' => 3]);
        $single = Product::query()->create(['name' => 'Пицца для наклеек', 'sort_order' => 201, 'minimum_quantity' => 1, 'packaging_mode' => 'single', 'is_active' => true]);
        Price::query()->create(['bakery_id' => $store->id, 'product_id' => $single->id, 'kopecks' => 4000]);
        $service = app(OrderingService::class);
        $quote = $service->quote($store->id, [$product->id => 20, $single->id => 2]);
        $service->place($store->id, (string) Str::uuid(), [$product->id => 20, $single->id => 2], $quote['hash']);
        $admin = User::factory()->create(['role' => UserRole::Admin, 'status' => UserStatus::Verify]);

        $this->actingAs($admin)->get('/admin?date='.$quote['date'].'&tab=labels')->assertOk()
            ->assertSee('Всего 6 шт.')
            ->assertSee('<td>Тестовый пирожок</td><td>—</td><td>—</td><td>—</td><td>4</td><td>4</td>', false)
            ->assertSee('<td>Пицца для наклеек</td><td>2</td><td>—</td><td>—</td><td>—</td><td>2</td>', false);
        $this->actingAs($admin)->get('/admin?date='.$quote['date'].'&tab=production')->assertOk()
            ->assertSee('Всего изделий: 22 шт.');
    }
}
