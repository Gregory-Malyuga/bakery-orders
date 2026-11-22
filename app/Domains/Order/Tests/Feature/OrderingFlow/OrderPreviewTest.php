<?php

namespace Domains\Order\Tests\Feature\OrderingFlow;

use Carbon\Carbon;
use Domains\Order\Application\OrderingService;
use Domains\Order\Application\Repositories\OrderRepositoryInterface;
use Domains\Order\Tests\Feature\Support\OrderingFlowCase;
use Domains\Price\Domain\Models\Price;
use Domains\Product\Domain\Models\Product;
use Domains\User\Domain\Enums\UserRole;
use Domains\User\Domain\Enums\UserStatus;
use Domains\User\Domain\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderPreviewTest extends OrderingFlowCase
{
    public function testEditingPreviewPreservesQuantitiesCommentAndRequestKey(): void
    {
        $this->withoutMiddleware(PreventRequestForgery::class);
        Carbon::setTestNow(Carbon::parse('2026-10-06 12:00:00', 'Europe/Moscow'));
        [$store, $user, $product] = $this->shop('Редактируемый заказ', 3000);
        $second = Product::query()->create(['name' => 'Второй товар для правки', 'sort_order' => 200, 'minimum_quantity' => 1, 'packaging_mode' => 'single', 'is_active' => true]);
        Price::query()->create(['bakery_id' => $store->id, 'product_id' => $second->id, 'kopecks' => 4000]);
        $key = (string) Str::uuid();
        $input = ['store' => $store->id, 'request_key' => $key, 'comment' => 'Без лука', 'quantities' => [$product->id => 3, $second->id => 2]];

        $this->actingAs($user)->post('/orders/preview', $input)->assertOk()->assertSee('Изменить количество');
        $this->actingAs($user)->post('/orders/edit-preview', $input)->assertOk()
            ->assertSee('name="quantities['.$product->id.']" value="3"', false)
            ->assertSee('name="quantities['.$second->id.']" value="2"', false)
            ->assertSee('name="request_key" value="'.$key.'"', false)
            ->assertSee('Без лука');
    }

    public function testStoreMinimumOverrideAndRepeatPrefillRespectAccessAndCurrentPrice(): void
    {
        $this->withoutMiddleware(PreventRequestForgery::class);
        Carbon::setTestNow(Carbon::parse('2026-10-06 12:00:00', 'Europe/Moscow'));
        [$store, $user, $product] = $this->shop('Повтор', 3000);
        [$other, $otherUser] = $this->shop('Другой', 4000);
        $admin = User::factory()->create(['role' => UserRole::Admin, 'status' => UserStatus::Verify]);
        $this->actingAs($admin)->post('/admin/products', ['id' => $product->id, 'name' => $product->name, 'sort_order' => 0, 'minimum_quantity' => 2, 'is_active' => 1])->assertRedirect();
        $this->actingAs($admin)->post('/admin/minimums', ['store' => $store->id, 'product' => $product->id, 'minimum_quantity' => 3])->assertRedirect();
        $this->assertSame(3, DB::table('bakery_store_product_minimums')->where('bakery_id', $store->id)->where('product_id', $product->id)->value('minimum_quantity'));
        $this->actingAs($user)->get('/orders')->assertOk()->assertSee('минимум 3 шт.');
        $this->actingAs($otherUser)->get('/orders')->assertOk()->assertSee('минимум 2 шт.');
        $this->actingAs($user)->post('/orders/preview', ['request_key' => (string) Str::uuid(), 'quantities' => [$product->id => 2]])->assertSessionHasErrors('quantities');
        $service = app(OrderingService::class);
        $quote = $service->quote($store->id, [$product->id => 3]);
        $order = $service->place($store->id, (string) Str::uuid(), [$product->id => 3], $quote['hash'])['order'];
        $this->assertNotNull($order);
        $this->actingAs($otherUser)->get('/orders/'.$order->id.'/repeat')->assertNotFound();
        Carbon::setTestNow(Carbon::parse('2026-10-07 12:00:00', 'Europe/Moscow'));
        Price::query()->where('bakery_id', $store->id)->where('product_id', $product->id)->update(['kopecks' => 3500]);
        $this->actingAs($user)->get('/orders/'.$order->id.'/repeat')->assertOk()->assertSee('value="3"', false)->assertSee('35,00 ₽');
        $this->assertCount(0, app(OrderRepositoryInterface::class)->orders('2026-10-08'));
        $this->actingAs($admin)->post('/admin/minimums', ['store' => $store->id, 'product' => $product->id, 'minimum_quantity' => ''])->assertRedirect();
        $this->actingAs($user)->get('/orders')->assertOk()->assertSee('минимум 2 шт.');
    }
}
