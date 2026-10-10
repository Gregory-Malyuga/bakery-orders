<?php

namespace Domains\Bakery\Tests\Feature;

use Domains\Bakery\Application\OrderingService;
use Domains\Bakery\Domain\Models\Bakery;
use Domains\Bakery\Domain\Models\Price;
use Domains\Bakery\Domain\Models\Product;
use Domains\User\Domain\Enums\UserRole;
use Domains\User\Domain\Enums\UserStatus;
use Domains\User\Domain\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PharData;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class OrderManagementTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_admin_adjusts_order_records_return_and_exports_period_report(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-10 12:00:00', 'Europe/Moscow'));
        $store = Bakery::query()->create(['name' => 'Точка', 'address' => 'Адрес', 'is_active' => true]);
        $pizza = Product::query()->create(['name' => 'Пицца', 'sort_order' => 1, 'minimum_quantity' => 1, 'is_active' => true]);
        $ham = Product::query()->create(['name' => 'Ветчина', 'sort_order' => 2, 'minimum_quantity' => 1, 'is_active' => true]);
        Price::query()->create(['bakery_id' => $store->id, 'product_id' => $pizza->id, 'kopecks' => 10000]);
        Price::query()->create(['bakery_id' => $store->id, 'product_id' => $ham->id, 'kopecks' => 7000]);
        $service = app(OrderingService::class);
        $quote = $service->quote($store->id, [$pizza->id => 2]);
        $order = $service->place($store->id, (string) Str::uuid(), [$pizza->id => 2], $quote['hash'])['order'];
        $this->assertNotNull($order);
        $admin = User::factory()->create(['role' => UserRole::Admin, 'status' => UserStatus::Verify]);

        $this->actingAs($admin)->get('/admin/orders/'.$order->id.'/edit')->assertOk()->assertSee('Изменить заказ');
        $this->actingAs($admin)->post('/admin/orders/'.$order->id.'/edit', [
            'quantities' => [$pizza->id => 3, $ham->id => 1],
            'reason' => 'Уточнение магазина',
            'admin_note' => 'Добавить к отгрузке',
        ])->assertRedirect();
        $this->assertDatabaseHas('bakery_orders', ['id' => $order->id, 'total_kopecks' => 37000, 'admin_note' => 'Добавить к отгрузке']);
        $this->assertDatabaseHas('bakery_order_adjustments', ['order_id' => $order->id, 'before_total_kopecks' => 20000, 'after_total_kopecks' => 37000]);

        $this->actingAs($admin)->post('/admin/orders/'.$order->id.'/returns', ['product' => $pizza->id, 'quantity' => 2, 'reason' => 'Обмен'])->assertRedirect();
        $this->actingAs($admin)->post('/admin/orders/'.$order->id.'/returns', ['product' => $pizza->id, 'quantity' => 2])->assertSessionHasErrors('quantity');
        $this->assertDatabaseCount('bakery_returns', 1);
        $this->actingAs($admin)->get('/admin/orders/'.$order->id)->assertOk()->assertSee('Уточнение магазина')->assertSee('Обмен');

        $period = '?from=2026-10-11&to=2026-10-11';
        $this->actingAs($admin)->get('/admin/report'.$period)->assertOk()->assertSee('370,00')->assertSee('200,00')->assertSee('170,00');
        $response = $this->actingAs($admin)->get('/admin/report/xlsx'.$period)->assertOk()->assertDownload('otchet-2026-10-11-2026-10-11.xlsx');
        $this->assertInstanceOf(BinaryFileResponse::class, $response->baseResponse);
        $path = $response->baseResponse->getFile()->getPathname();
        $archive = new PharData($path);
        $this->assertStringContainsString('Пицца', $archive['xl/worksheets/sheet1.xml']->getContent());
        $this->assertStringContainsString('После возврата, ₽', $archive['xl/worksheets/sheet1.xml']->getContent());
        $this->assertStringContainsString('<v>2</v>', $archive['xl/worksheets/sheet1.xml']->getContent());
        $this->assertStringContainsString('Помидоры', $archive['xl/worksheets/sheet3.xml']->getContent());
        unset($archive);
        unlink($path);
    }

    public function test_shared_account_uses_first_stores_prices_for_both_stores(): void
    {
        $first = Bakery::query()->create(['name' => 'Колпино 1', 'address' => '1', 'is_active' => true]);
        $second = Bakery::query()->create(['name' => 'Колпино 2', 'address' => '2', 'is_active' => true]);
        $product = Product::query()->create(['name' => 'Пирожок', 'sort_order' => 1, 'minimum_quantity' => 1, 'is_active' => true]);
        Price::query()->create(['bakery_id' => $first->id, 'product_id' => $product->id, 'kopecks' => 3000]);
        Price::query()->create(['bakery_id' => $second->id, 'product_id' => $product->id, 'kopecks' => 4000]);
        $admin = User::factory()->create(['role' => UserRole::Admin, 'status' => UserStatus::Verify]);
        $this->actingAs($admin)->post('/admin/accounts', [
            'stores' => [$first->id, $second->id], 'name' => 'Колпино', 'email' => 'kolpino@example.test', 'password' => 'StrongPassword123!', 'is_active' => 1,
        ])->assertRedirect();
        $this->assertDatabaseHas('bakeries', ['id' => $second->id, 'price_source_id' => $first->id]);
        $service = app(OrderingService::class);
        $this->assertSame(3000, $service->quote($second->id, [$product->id => 1])['total']);

        $this->actingAs($admin)->post('/admin/prices', ['store' => $second->id, 'product' => $product->id, 'rubles' => '35,00'])->assertRedirect();
        $this->assertEquals(3500, DB::table('bakery_prices')->where('bakery_id', $first->id)->where('product_id', $product->id)->value('kopecks'));
        $this->assertSame(3500, $service->quote($second->id, [$product->id => 1])['total']);

        $account = User::query()->where('email', 'kolpino@example.test')->firstOrFail();
        $this->actingAs($admin)->post('/admin/accounts', [
            'id' => $account->id, 'stores' => [$second->id], 'name' => 'Колпино', 'email' => 'kolpino@example.test', 'is_active' => 1,
        ])->assertRedirect();
        $this->assertDatabaseHas('bakeries', ['id' => $second->id, 'price_source_id' => null]);
        $this->assertSame(3500, $service->quote($second->id, [$product->id => 1])['total']);
    }
}
