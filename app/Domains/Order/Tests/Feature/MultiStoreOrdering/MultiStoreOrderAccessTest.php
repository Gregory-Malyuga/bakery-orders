<?php

namespace Domains\Order\Tests\Feature\MultiStoreOrdering;

use Domains\Order\Application\OrderingService;
use Domains\Store\Domain\Models\Store;
use Domains\Price\Domain\Models\Price;
use Domains\Product\Domain\Models\Product;
use Domains\Order\Tests\Feature\Support\MultiStoreOrderingCase;
use Domains\User\Domain\Enums\UserRole;
use Domains\User\Domain\Enums\UserStatus;
use Domains\User\Domain\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PharData;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MultiStoreOrderAccessTest extends MultiStoreOrderingCase
{
    public function testAccountCanOrderForAssignedStoresOnlyAndCommentReachesInvoice(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-10 12:00:00', 'Europe/Moscow'));
        $first = Store::query()->create(['name' => 'Колпино, Седова', 'address' => 'Седова', 'is_active' => true]);
        $second = Store::query()->create(['name' => 'Колпино, Финляндская', 'address' => 'Финляндская', 'is_active' => true]);
        $other = Store::query()->create(['name' => 'Балтийская', 'address' => 'Балтийская', 'is_active' => true]);
        $product = Product::query()->create(['name' => 'Пирожок', 'sort_order' => 1, 'minimum_quantity' => 1, 'packaging_mode' => 'single', 'is_active' => true]);
        foreach ([$first, $second, $other] as $store) {
            Price::query()->create(['bakery_id' => $store->id, 'product_id' => $product->id, 'kopecks' => 3000]);
        }
        $user = User::factory()->create(['role' => UserRole::User, 'status' => UserStatus::Verify, 'bakery_id' => $first->id]);
        DB::table('bakery_account_stores')->insert([
            ['user_id' => $user->id, 'bakery_id' => $first->id],
            ['user_id' => $user->id, 'bakery_id' => $second->id],
        ]);

        $this->actingAs($user)->get('/orders?store='.$second->id)->assertOk()->assertSee('Колпино, Финляндская');
        $this->actingAs($user)->get('/orders?store='.$other->id)->assertForbidden();
        $this->actingAs($user)->post('/orders/preview', ['store' => $other->id, 'request_key' => (string) Str::uuid(), 'quantities' => [$product->id => 1]])->assertForbidden();

        $service = app(OrderingService::class);
        $quote = $service->quote($second->id, [$product->id => 2]);
        $order = $this->actingAs($user)->post('/orders', [
            'store' => $second->id,
            'request_key' => (string) Str::uuid(),
            'quantities' => [$product->id => 2],
            'quote_hash' => $quote['hash'],
            'comment' => 'Обмен: один пирожок с капустой',
        ])->assertRedirect()->assertSessionHas('success');
        $location = $order->headers->get('Location');
        $this->assertNotNull($location);
        $orderId = (int) basename($location);
        $this->assertDatabaseHas('bakery_orders', ['id' => $orderId, 'bakery_id' => $second->id, 'comment' => 'Обмен: один пирожок с капустой']);
        $this->actingAs($user)->get('/orders/'.$orderId)->assertOk()->assertSee('Обмен: один пирожок с капустой');
        $outsider = User::factory()->create(['role' => UserRole::User, 'status' => UserStatus::Verify, 'bakery_id' => $other->id]);
        $this->actingAs($outsider)->get('/orders/'.$orderId)->assertNotFound();

        $admin = User::factory()->create(['role' => UserRole::Admin, 'status' => UserStatus::Verify]);
        $this->actingAs($admin)->get('/admin/orders/'.$orderId.'/print')->assertOk()->assertSee('Обмен: один пирожок с капустой');
        $download = $this->actingAs($admin)->get('/admin/orders/'.$orderId.'/xlsx')->assertOk();
        $this->assertInstanceOf(BinaryFileResponse::class, $download->baseResponse);
        $path = $download->baseResponse->getFile()->getPathname();
        $archive = new PharData($path);
        $this->assertStringContainsString('Обмен: один пирожок с капустой', $archive['xl/worksheets/sheet1.xml']->getContent());
        unset($archive);
        unlink($path);
    }

    public function testOneAccountCanPlaceOneOrderPerStoreForTheSameDeliveryDate(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-10 12:00:00', 'Europe/Moscow'));
        $first = Store::query()->create(['name' => 'Тестовый магазин А', 'address' => 'Адрес А', 'is_active' => true]);
        $second = Store::query()->create(['name' => 'Тестовый магазин Б', 'address' => 'Адрес Б', 'is_active' => true]);
        $product = Product::query()->create(['name' => 'Тестовый пирожок', 'sort_order' => 1, 'minimum_quantity' => 1, 'packaging_mode' => 'single', 'is_active' => true]);
        foreach ([$first, $second] as $store) {
            Price::query()->create(['bakery_id' => $store->id, 'product_id' => $product->id, 'kopecks' => 3000]);
        }
        $account = User::factory()->create(['role' => UserRole::User, 'status' => UserStatus::Verify, 'bakery_id' => $first->id]);
        DB::table('bakery_account_stores')->insert([
            ['user_id' => $account->id, 'bakery_id' => $first->id],
            ['user_id' => $account->id, 'bakery_id' => $second->id],
        ]);

        $quote = app(OrderingService::class)->quote($first->id, [$product->id => 1]);
        $firstResponse = $this->actingAs($account)->post('/orders', [
            'store' => $first->id, 'request_key' => (string) Str::uuid(),
            'quantities' => [$product->id => 1], 'quote_hash' => $quote['hash'],
        ])->assertRedirect();
        $this->actingAs($account)->get('/orders?store='.$first->id)
            ->assertOk()->assertSee('Для магазина «Тестовый магазин А» заказ на завтра уже оформлен')
            ->assertSee('/orders?store='.$second->id, false);
        $this->actingAs($account)->get('/orders?store='.$second->id)
            ->assertOk()->assertSee('Проверить заказ');

        $secondResponse = $this->actingAs($account)->post('/orders', [
            'store' => $second->id, 'request_key' => (string) Str::uuid(),
            'quantities' => [$product->id => 1], 'quote_hash' => $quote['hash'],
        ])->assertRedirect();
        $this->assertNotSame($firstResponse->headers->get('Location'), $secondResponse->headers->get('Location'));
        $this->assertSame(2, DB::table('bakery_orders')->whereIn('bakery_id', [$first->id, $second->id])
            ->where('delivery_date', $quote['date'])->where('status', 'accepted')->count());

        $duplicate = $this->actingAs($account)->post('/orders', [
            'store' => $first->id, 'request_key' => (string) Str::uuid(),
            'quantities' => [$product->id => 1], 'quote_hash' => $quote['hash'],
        ])->assertRedirect();
        $this->assertSame($firstResponse->headers->get('Location'), $duplicate->headers->get('Location'));
        $this->assertSame(2, DB::table('bakery_orders')->whereIn('bakery_id', [$first->id, $second->id])
            ->where('delivery_date', $quote['date'])->where('status', 'accepted')->count());
        Carbon::setTestNow();
    }

    public function testSharedPriceDoesNotForceTheSameAssortmentOnEveryStore(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-10 12:00:00', 'Europe/Moscow'));
        $first = Store::query()->create(['name' => 'Тестовый магазин 1', 'address' => 'Адрес 1', 'is_active' => true]);
        $second = Store::query()->create(['name' => 'Тестовый магазин 2', 'address' => 'Адрес 2', 'is_active' => true]);
        DB::table('bakeries')->where('id', $second->id)->update(['price_source_id' => $first->id]);
        $product = Product::query()->create(['name' => 'Тестовый товар', 'sort_order' => 1, 'minimum_quantity' => 1, 'packaging_mode' => 'single', 'is_active' => true]);
        Price::query()->create(['bakery_id' => $first->id, 'product_id' => $product->id, 'kopecks' => 3000]);
        $account = User::factory()->create(['role' => UserRole::User, 'status' => UserStatus::Verify, 'bakery_id' => $first->id]);
        DB::table('bakery_account_stores')->insert([
            ['user_id' => $account->id, 'bakery_id' => $first->id],
            ['user_id' => $account->id, 'bakery_id' => $second->id],
        ]);
        $admin = User::factory()->create(['role' => UserRole::Admin, 'status' => UserStatus::Verify]);

        $this->actingAs($admin)->post('/admin/availability', [
            'store' => $second->id, 'product' => $product->id, 'is_available' => 0,
        ])->assertRedirect()->assertSessionHas('success');
        $this->assertDatabaseHas('bakery_store_product_exclusions', ['bakery_id' => $second->id, 'product_id' => $product->id]);
        $this->actingAs($account)->get('/orders?store='.$second->id)->assertOk()
            ->assertSee('Нет цены или товар не поставляется — заказать нельзя');
        $this->actingAs($account)->post('/orders/preview', [
            'store' => $second->id, 'request_key' => (string) Str::uuid(), 'quantities' => [$product->id => 1],
        ])->assertSessionHasErrors('quantities');
        $this->actingAs($account)->post('/orders/preview', [
            'store' => $first->id, 'request_key' => (string) Str::uuid(), 'quantities' => [$product->id => 1],
        ])->assertOk();
        $this->actingAs($admin)->get('/admin/catalog?tab=prices&store='.$second->id)->assertOk()
            ->assertSee('30,00');

        $this->actingAs($admin)->post('/admin/availability', [
            'store' => $second->id, 'product' => $product->id, 'is_available' => 1,
        ])->assertRedirect();
        $this->assertDatabaseMissing('bakery_store_product_exclusions', ['bakery_id' => $second->id, 'product_id' => $product->id]);
        $this->actingAs($account)->post('/orders/preview', [
            'store' => $second->id, 'request_key' => (string) Str::uuid(), 'quantities' => [$product->id => 1],
        ])->assertOk();
        Carbon::setTestNow();
    }
}
