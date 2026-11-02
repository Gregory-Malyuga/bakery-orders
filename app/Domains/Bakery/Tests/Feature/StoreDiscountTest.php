<?php

namespace Domains\Bakery\Tests\Feature;

use Domains\Bakery\Application\AnalyticsService;
use Domains\Bakery\Application\OrderingService;
use Domains\Bakery\Application\Repositories\OrderingRepositoryInterface;
use Domains\Bakery\Domain\Models\Bakery;
use Domains\Bakery\Domain\Models\Order;
use Domains\Bakery\Domain\Models\OrderItem;
use Domains\Bakery\Domain\Models\Price;
use Domains\Bakery\Domain\Models\Product;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class StoreDiscountTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_discount_is_rounded_to_kopecks_per_unit_and_isolated_per_store(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-01 12:00:00', 'Europe/Moscow'));
        $discounted = Bakery::query()->create([
            'name' => 'СНТ Надежда тест', 'address' => 'Адрес',
            'is_active' => true, 'discount_enabled' => true, 'discount_percent' => 10,
        ]);
        $regular = Bakery::query()->create([
            'name' => 'Другая точка', 'address' => 'Другой адрес', 'is_active' => true,
        ]);
        $product = Product::query()->create([
            'name' => 'Проверочный товар', 'sort_order' => 1, 'minimum_quantity' => 1,
            'packaging_mode' => 'single', 'is_active' => true,
        ]);
        Price::query()->create(['bakery_id' => $discounted->id, 'product_id' => $product->id, 'kopecks' => 12345]);
        Price::query()->create(['bakery_id' => $regular->id, 'product_id' => $product->id, 'kopecks' => 12345]);

        $repo = app(OrderingRepositoryInterface::class);
        $this->assertSame(12345, $repo->prices($discounted->id, true, false)[$product->id]);
        $this->assertSame(11111, $repo->prices($discounted->id)[$product->id]);
        $this->assertSame(12345, $repo->prices($regular->id)[$product->id]);

        $service = app(OrderingService::class);
        $quote = $service->quote($discounted->id, [$product->id => 3]);
        $this->assertSame(33333, $quote['total']);
        $order = $service->place($discounted->id, (string) Str::uuid(), [$product->id => 3], $quote['hash'])['order'];
        $this->assertInstanceOf(Order::class, $order);
        $item = $order->items->first();
        $this->assertInstanceOf(OrderItem::class, $item);
        $this->assertSame(11111, (int) $item->unit_kopecks);
        $this->assertSame(33333, (int) $order->total_kopecks);

        $discounted->update(['discount_enabled' => false, 'discount_percent' => 0]);
        $this->assertSame(12345, $repo->prices($discounted->id)[$product->id]);
        $reloaded = $order->fresh();
        $this->assertInstanceOf(Order::class, $reloaded);
        $reloadedItem = $reloaded->items->first();
        $this->assertInstanceOf(OrderItem::class, $reloadedItem);
        $this->assertSame(11111, (int) $reloadedItem->unit_kopecks);
        $result = app(AnalyticsService::class)->build($quote['date'], $quote['date'], $discounted->id);
        $this->assertSame(33333, $result['summary']['gross']);
    }

    public function test_discount_rounds_half_kopeck_up_without_floating_point(): void
    {
        $store = Bakery::query()->create([
            'name' => 'Точка округления', 'address' => 'Адрес',
            'is_active' => true, 'discount_enabled' => true, 'discount_percent' => 50,
        ]);
        $product = Product::query()->create([
            'name' => 'Малая цена', 'sort_order' => 1, 'minimum_quantity' => 1,
            'packaging_mode' => 'single', 'is_active' => true,
        ]);
        Price::query()->create(['bakery_id' => $store->id, 'product_id' => $product->id, 'kopecks' => 1]);
        $this->assertSame(1, app(OrderingRepositoryInterface::class)->prices($store->id)[$product->id]);
    }
    public function test_shared_base_price_only_discounts_selected_store(): void
    {
        $source = Bakery::query()->create([
            'name' => 'Общий прайс', 'address' => 'Адрес 1', 'is_active' => true,
        ]);
        $discounted = Bakery::query()->create([
            'name' => 'Точка со скидкой', 'address' => 'Адрес 2', 'is_active' => true,
            'discount_enabled' => true, 'discount_percent' => 10,
        ]);
        $discounted->price_source_id = $source->id;
        $discounted->save();
        $product = Product::query()->create([
            'name' => 'Изделие с общим прайсом', 'sort_order' => 1,
            'minimum_quantity' => 1, 'packaging_mode' => 'single', 'is_active' => true,
        ]);
        Price::query()->create([
            'bakery_id' => $source->id, 'product_id' => $product->id, 'kopecks' => 1099,
        ]);

        $prices = app(OrderingRepositoryInterface::class);
        $this->assertSame(1099, $prices->prices($source->id)[$product->id]);
        $this->assertSame(989, $prices->prices($discounted->id)[$product->id]);
        $this->assertSame(1099, $prices->prices($discounted->id, true, false)[$product->id]);
    }

    public function test_zero_and_full_discount_boundaries(): void
    {
        $store = Bakery::query()->create([
            'name' => 'Граничные скидки', 'address' => 'Адрес', 'is_active' => true,
            'discount_enabled' => true, 'discount_percent' => 0,
        ]);
        $product = Product::query()->create([
            'name' => 'Товар для границ', 'sort_order' => 1,
            'minimum_quantity' => 1, 'packaging_mode' => 'single', 'is_active' => true,
        ]);
        Price::query()->create([
            'bakery_id' => $store->id, 'product_id' => $product->id, 'kopecks' => 12345,
        ]);
        $prices = app(OrderingRepositoryInterface::class);

        $this->assertSame(12345, $prices->prices($store->id)[$product->id]);
        $store->update(['discount_percent' => 100]);
        $this->assertSame(0, $prices->prices($store->id)[$product->id]);
        $store->update(['discount_enabled' => false]);
        $this->assertSame(12345, $prices->prices($store->id)[$product->id]);
    }

}
