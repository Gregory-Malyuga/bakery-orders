<?php

namespace Domains\Order\Tests\Feature\OrderingFlow;

use Carbon\Carbon;
use Domains\Order\Application\OrderingService;
use Domains\Order\Application\Repositories\OrderRepositoryInterface;
use Domains\Order\Tests\Feature\Support\OrderingFlowCase;
use Domains\Price\Domain\Models\Price;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrderSubmissionTest extends OrderingFlowCase
{
    public function testPersonalPricesSnapshotAndDuplicateSubmission(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-06 12:00:00', 'Europe/Moscow'));
        [$firstStore, $firstUser, $product] = $this->shop('Первый', 3000);
        [$secondStore, $secondUser] = $this->shop('Второй', 3500);
        $service = app(OrderingService::class);
        $firstQuote = $service->quote($firstStore->id, [$product->id => 2]);
        $secondQuote = $service->quote($secondStore->id, [$product->id => 2]);
        $this->assertSame(6000, $firstQuote['total']);
        $this->assertSame(7000, $secondQuote['total']);
        $key = (string) Str::uuid();
        $first = $service->place($firstStore->id, $key, [$product->id => 2], $firstQuote['hash'])['order'];
        $this->assertNotNull($first);
        $again = $service->place($firstStore->id, $key, [$product->id => 2], $firstQuote['hash'])['order'];
        $this->assertSame($first->id, $again?->id);
        Price::query()->where('bakery_id', $firstStore->id)->where('product_id', $product->id)->update(['kopecks' => 9900]);
        $this->assertSame(3000, app(OrderRepositoryInterface::class)->order($first->id)?->items->first()?->unit_kopecks);
        $this->actingAs($secondUser)->get('/orders/'.$first->id)->assertNotFound();
        $this->actingAs($firstUser)->get('/orders/'.$first->id)->assertOk();
    }

    public function testChangedPriceRequiresFreshConfirmationAndCutoffRejectsSubmission(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-06 18:29:59', 'Europe/Moscow'));
        [$store, $user, $product] = $this->shop('Четвёртый', 3000);
        $service = app(OrderingService::class);
        $quote = $service->quote($store->id, [$product->id => 2]);
        Price::query()->where('bakery_id', $store->id)->where('product_id', $product->id)->update(['kopecks' => 4000]);
        $key = (string) Str::uuid();
        $result = $service->place($store->id, $key, [$product->id => 2], $quote['hash']);
        $this->assertNull($result['order']);
        $freshQuote = $result['quote'];
        $this->assertNotNull($freshQuote);
        $this->assertSame(8000, $freshQuote['total']);
        $this->assertCount(0, app(OrderRepositoryInterface::class)->orders($quote['date']));
        $this->actingAs($user)->get('/admin/catalog')->assertForbidden();

        Carbon::setTestNow(Carbon::parse('2026-10-06 18:30:00', 'Europe/Moscow'));
        $this->expectException(ValidationException::class);
        $service->place($store->id, $key, [$product->id => 2], $freshQuote['hash']);
    }

    public function testOrderPlacementIsBlockedWhenCacheLockIsHeld(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-01 12:00:00', 'Europe/Moscow'));
        [$store, , $product] = $this->shop('Пекарня с локом', 3000);

        $service = app(OrderingService::class);
        $quote = $service->quote($store->id, [$product->id => 1]);
        $requestKey = (string) Str::uuid();

        $lockKey = "create_order_lock:{$store->id}:".$quote['date'];
        $lock = Cache::lock($lockKey, 10);
        $this->assertTrue($lock->get());

        try {
            try {
                $service->place($store->id, $requestKey, [$product->id => 1], $quote['hash']);
                $this->fail('Ожидалось исключение при занятом локе кэша.');
            } catch (ValidationException $e) {
                $this->assertDatabaseMissing('bakery_orders', ['request_key' => $requestKey]);
            }
        } finally {
            $lock->release();
        }

        $result = $service->place($store->id, $requestKey, [$product->id => 1], $quote['hash']);
        $this->assertNotNull($result['order']);
        $this->assertDatabaseHas('bakery_orders', ['request_key' => $requestKey]);
    }
}
