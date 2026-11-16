<?php

namespace Domains\Bakery\Tests\Feature;

use Carbon\Carbon;
use Domains\Bakery\Application\OrderingService;
use Domains\Bakery\Application\Repositories\OrderingRepositoryInterface;
use Domains\Bakery\Domain\Models\Bakery;
use Domains\Bakery\Domain\Models\Price;
use Domains\Bakery\Domain\Models\Product;
use Domains\User\Domain\Enums\UserRole;
use Domains\User\Domain\Enums\UserStatus;
use Domains\User\Domain\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PharData;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class OrderingFlowTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_guest_is_redirected_to_login_from_admin(): void
    {
        $this->get('/admin')->assertRedirect('/login');
    }

    /** @return array{0:Bakery,1:User,2:Product} */
    private function shop(string $name, int $kopecks): array
    {
        $store = Bakery::query()->create(['name' => $name, 'address' => $name.' 1', 'is_active' => true]);
        $user = User::factory()->create(['role' => UserRole::User, 'status' => UserStatus::Verify, 'bakery_id' => $store->id]);
        $product = Product::query()->firstOrCreate(['name' => 'Тестовый пирожок'], ['is_active' => true]);
        Price::query()->updateOrCreate(['bakery_id' => $store->id, 'product_id' => $product->id], ['kopecks' => $kopecks]);

        return [$store, $user, $product];
    }

    public function test_personal_prices_snapshot_and_duplicate_submission(): void
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
        $this->assertSame(3000, app(OrderingRepositoryInterface::class)->order($first->id)?->items->first()?->unit_kopecks);
        $this->actingAs($secondUser)->get('/orders/'.$first->id)->assertNotFound();
        $this->actingAs($firstUser)->get('/orders/'.$first->id)->assertOk();
    }

    public function test_cutoff_and_cancellation_remove_order_from_summary_and_print(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-06 18:29:59', 'Europe/Moscow'));
        [$store, $user, $product] = $this->shop('Третий', 3200);
        $service = app(OrderingService::class);
        $quote = $service->quote($store->id, [$product->id => 1]);
        $order = $service->place($store->id, (string) Str::uuid(), [$product->id => 1], $quote['hash'])['order'];
        $this->assertNotNull($order);
        $service->cancel($store->id, $order->id);
        $this->assertCount(0, app(OrderingRepositoryInterface::class)->orders($quote['date']));
        $admin = User::factory()->create(['role' => UserRole::Admin, 'status' => UserStatus::Verify]);
        $this->actingAs($admin)->get('/admin/print?date='.$quote['date'])->assertOk()->assertDontSee('Третий');
        Carbon::setTestNow(Carbon::parse('2026-10-06 18:30:00', 'Europe/Moscow'));
        $this->assertFalse($service->isOpen());
        $this->actingAs($user)->get('/orders')->assertOk()->assertSee('Приём закрыт');
    }

    public function test_changed_price_requires_fresh_confirmation_and_cutoff_rejects_submission(): void
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
        $this->assertCount(0, app(OrderingRepositoryInterface::class)->orders($quote['date']));
        $this->actingAs($user)->get('/admin/catalog')->assertForbidden();

        Carbon::setTestNow(Carbon::parse('2026-10-06 18:30:00', 'Europe/Moscow'));
        $this->expectException(ValidationException::class);
        $service->place($store->id, $key, [$product->id => 2], $freshQuote['hash']);
    }

    public function test_product_minimum_is_enforced_on_preview_and_final_submission(): void
    {
        $this->withoutMiddleware(PreventRequestForgery::class);
        Carbon::setTestNow(Carbon::parse('2026-10-06 12:00:00', 'Europe/Moscow'));
        [$store, $user, $product] = $this->shop('Пятый', 3000);
        $service = app(OrderingService::class);
        $oldQuote = $service->quote($store->id, [$product->id => 2]);
        $admin = User::factory()->create(['role' => UserRole::Admin, 'status' => UserStatus::Verify]);
        $this->actingAs($admin)->post('/admin/products', [
            'id' => $product->id,
            'name' => $product->name,
            'sort_order' => 0,
            'minimum_quantity' => 3,
            'is_active' => 1,
        ])->assertRedirect();
        $this->assertSame(3, $product->fresh()?->minimum_quantity);

        $this->actingAs($user)->post('/orders/preview', [
            'request_key' => (string) Str::uuid(),
            'quantities' => [$product->id => 2],
        ])->assertSessionHasErrors('quantities');
        $this->actingAs($user)->post('/orders', [
            'request_key' => (string) Str::uuid(),
            'quantities' => [$product->id => 2],
            'quote_hash' => $oldQuote['hash'],
        ])->assertSessionHasErrors('quantities');
        $this->assertCount(0, app(OrderingRepositoryInterface::class)->orders($oldQuote['date']));

        $newQuote = $service->quote($store->id, [$product->id => 3]);
        $this->assertSame(9000, $newQuote['total']);
        $this->assertNotNull($service->place($store->id, (string) Str::uuid(), [$product->id => 3], $newQuote['hash'])['order']);
    }

    public function test_store_minimum_override_and_repeat_prefill_respect_access_and_current_price(): void
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
        $this->assertCount(0, app(OrderingRepositoryInterface::class)->orders('2026-10-08'));
        $this->actingAs($admin)->post('/admin/minimums', ['store' => $store->id, 'product' => $product->id, 'minimum_quantity' => ''])->assertRedirect();
        $this->actingAs($user)->get('/orders')->assertOk()->assertSee('минимум 2 шт.');
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
        $this->assertCount(0, app(OrderingRepositoryInterface::class)->orders($quote['date']));
    }

    public function test_excel_download_contains_only_accepted_invoice_snapshots(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-06 12:00:00', 'Europe/Moscow'));
        [$firstStore, $storeUser, $product] = $this->shop('Седьмой', 3150);
        [$secondStore] = $this->shop('Восьмой', 3200);
        [$thirdStore] = $this->shop('Девятый', 3400);
        $service = app(OrderingService::class);
        $quote = $service->quote($firstStore->id, [$product->id => 2]);
        $accepted = $service->place($firstStore->id, (string) Str::uuid(), [$product->id => 2], $quote['hash'])['order'];
        $this->assertNotNull($accepted);
        $otherQuote = $service->quote($secondStore->id, [$product->id => 1]);
        $cancelled = $service->place($secondStore->id, (string) Str::uuid(), [$product->id => 1], $otherQuote['hash'])['order'];
        $this->assertNotNull($cancelled);
        $service->cancel($secondStore->id, $cancelled->id);
        $thirdQuote = $service->quote($thirdStore->id, [$product->id => 3]);
        $this->assertNotNull($service->place($thirdStore->id, (string) Str::uuid(), [$product->id => 3], $thirdQuote['hash'])['order']);
        Price::query()->where('bakery_id', $firstStore->id)->where('product_id', $product->id)->update(['kopecks' => 9900]);

        $this->actingAs($storeUser)->get('/admin/xlsx?date='.$quote['date'])->assertForbidden();
        $admin = User::factory()->create(['role' => UserRole::Admin, 'status' => UserStatus::Verify]);
        $response = $this->actingAs($admin)->get('/admin/xlsx?date='.$quote['date'])->assertOk()->assertDownload('nakladnye-'.$quote['date'].'.xlsx');
        $this->assertInstanceOf(BinaryFileResponse::class, $response->baseResponse);
        $path = $response->baseResponse->getFile()->getPathname();
        $archive = new PharData($path);
        $sheet = $archive['xl/worksheets/sheet1.xml']->getContent();
        $summary = $archive['xl/worksheets/sheet2.xml']->getContent();
        $styles = $archive['xl/styles.xml']->getContent();
        $this->assertStringContainsString('<fills count="4">', $styles);
        $this->assertStringContainsString('fgColor rgb="FFFFFF00"', $styles);
        $this->assertStringContainsString('fgColor rgb="FFE2F0D9"', $styles);
        $this->assertStringContainsString('<borders count="2">', $styles);
        $this->assertStringContainsString('r="A6" s="5"', $sheet);
        $this->assertStringContainsString('r="A7" s="4"', $sheet);
        $this->assertStringContainsString('r="A3" s="5"', $summary);
        $this->assertStringContainsString('Седьмой', $sheet);
        $this->assertStringContainsString('Девятый', $sheet);
        $this->assertStringContainsString('r="J4"', $sheet);
        $this->assertStringContainsString('<v>31.50</v>', $sheet);
        $this->assertStringContainsString('<v>34.00</v>', $sheet);
        $this->assertStringNotContainsString('Восьмой', $sheet);
        $this->assertStringContainsString('Заказ на ', $summary);
        $this->assertStringContainsString('Итого, ₽', $summary);
        $this->assertStringContainsString('<v>165.00</v>', $summary);
        $this->assertStringNotContainsString('Восьмой', $summary);
        $this->actingAs($admin)->get('/admin/orders/'.$cancelled->id.'/xlsx')->assertNotFound();
        unset($archive);
        unlink($path);
    }
}
