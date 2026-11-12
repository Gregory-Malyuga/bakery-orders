<?php

namespace Domains\Bakery\Tests\Feature;

use Carbon\Carbon;
use Domains\Bakery\Domain\Models\Product;
use Domains\User\Domain\Enums\UserRole;
use Domains\User\Domain\Enums\UserStatus;
use Domains\User\Domain\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class StockLedgerTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_ledger_keeps_exact_balances_and_inventory_discrepancies_without_recipe_writeoffs(): void
    {
        $this->withoutMiddleware(PreventRequestForgery::class);
        Carbon::setTestNow(Carbon::parse('2026-11-01 12:00:00', 'Europe/Moscow'));
        $admin = User::factory()->create(['role' => UserRole::Admin, 'status' => UserStatus::Verify]);
        $storeUser = User::factory()->create(['role' => UserRole::User, 'status' => UserStatus::Verify]);
        $this->actingAs($storeUser)->get('/admin/stock')->assertForbidden();
        $this->actingAs($admin)->get('/admin/stock')->assertOk()->assertSee('Заготовки');
        $item = DB::table('bakery_stock_items')->where('kind', 'ingredient')->first();
        $this->assertNotNull($item);
        $post = fn (string $kind, string $quantity) => $this->actingAs($admin)->post('/admin/stock/movements', [
            'item' => $item->id, 'kind' => $kind, 'quantity' => $quantity, 'reason' => 'Контрольный документ',
        ]);

        $post('opening', '10,500')->assertRedirect();
        $post('receipt', '2.250')->assertRedirect();
        $post('consumption', '3.125')->assertRedirect();
        $post('writeoff', '10')->assertSessionHasErrors('quantity');
        $this->assertSame(9625, (int) DB::table('bakery_stock_movements')->where('stock_item_id', $item->id)->sum('quantity_milli'));

        Carbon::setTestNow(Carbon::parse('2026-11-02 12:00:00', 'Europe/Moscow'));
        $post('inventory', '9')->assertRedirect();
        $this->actingAs($admin)->get('/admin/stock?date=2026-11-01')->assertOk()->assertSee('9,625');
        $this->actingAs($admin)->get('/admin/stock?date=2026-11-02')->assertOk()->assertSee('9,000')->assertSee('-0,625');
        $this->assertSame(9000, (int) DB::table('bakery_stock_movements')->where('stock_item_id', $item->id)->sum('quantity_milli'));
        /** @var object{id:int}|null $receipt */
        $receipt = DB::table('bakery_stock_movements')->where('stock_item_id', $item->id)->where('kind', 'receipt')->first();
        $this->assertNotNull($receipt);
        $this->actingAs($admin)->post('/admin/stock/movements/'.$receipt->id.'/reverse', ['reason' => 'Ошибка прихода'])->assertRedirect();
        $this->assertSame(6750, (int) DB::table('bakery_stock_movements')->where('stock_item_id', $item->id)->sum('quantity_milli'));
        $this->actingAs($admin)->post('/admin/stock/movements/'.$receipt->id.'/reverse', ['reason' => 'Повтор'])->assertSessionHasErrors('movement');
        $this->assertSame(5, DB::table('bakery_stock_movements')->where('stock_item_id', $item->id)->count());
    }

    public function test_catalog_includes_new_packaging_and_finished_products(): void
    {
        $this->withoutMiddleware(PreventRequestForgery::class);
        $admin = User::factory()->create(['role' => UserRole::Admin, 'status' => UserStatus::Verify]);
        $product = Product::query()->create(['name' => 'Складское изделие', 'is_active' => true, 'packaging_mode' => 'single']);
        $this->actingAs($admin)->post('/admin/stock/packaging', ['name' => 'Коробка', 'unit' => 'pcs'])->assertRedirect();
        $this->actingAs($admin)->get('/admin/stock')->assertOk()->assertSee('Коробка');
        $this->assertDatabaseHas('bakery_stock_items', ['kind' => 'packaging', 'name' => 'Коробка', 'unit' => 'pcs']);
        $this->assertDatabaseHas('bakery_stock_items', ['kind' => 'product', 'product_id' => $product->id, 'unit' => 'pcs']);
    }

    public function test_stock_price_measure_starts_tomorrow_and_keeps_daily_history(): void
    {
        $this->withoutMiddleware(PreventRequestForgery::class);
        Carbon::setTestNow(Carbon::parse('2026-11-10 14:00:00', 'Europe/Moscow'));
        $admin = User::factory()->create(['role' => UserRole::Admin, 'status' => UserStatus::Verify]);
        $this->actingAs($admin)->get('/admin/stock')->assertOk();
        $item = DB::table('bakery_stock_items')->where('kind', 'ingredient')->where('unit', 'g')->first();
        $this->assertNotNull($item);
        $this->actingAs($admin)->post('/admin/stock/movements', [
            'item' => $item->id, 'kind' => 'opening', 'quantity' => '500', 'reason' => 'Проверка',
        ])->assertRedirect();
        $this->actingAs($admin)->post('/admin/stock/prices', [
            'item' => $item->id, 'price' => '35,00', 'measure' => 'custom', 'custom_quantity' => '70', 'custom_unit' => 'g',
        ])->assertRedirect();
        $this->actingAs($admin)->get('/admin/stock?date=2026-11-10')->assertOk()->assertSee('Не задана');
        Carbon::setTestNow(Carbon::parse('2026-11-11 12:00:00', 'Europe/Moscow'));
        $this->actingAs($admin)->get('/admin/stock?date=2026-11-11')->assertOk()->assertSee('250,00 ₽');
        $this->actingAs($admin)->post('/admin/stock/prices', [
            'item' => $item->id, 'price' => '40', 'measure' => '100g',
        ])->assertRedirect();
        $this->actingAs($admin)->get('/admin/stock?date=2026-11-11')->assertOk()->assertSee('250,00 ₽');
        Carbon::setTestNow(Carbon::parse('2026-11-12 12:00:00', 'Europe/Moscow'));
        $this->actingAs($admin)->get('/admin/stock?date=2026-11-12')->assertOk()->assertSee('200,00 ₽');
        $this->assertSame(2, DB::table('bakery_stock_daily_prices')->where('stock_item_id', $item->id)->count());
        $this->actingAs($admin)->get('/admin/analytics?from=2026-11-10&to=2026-11-12')->assertOk()
            ->assertSeeInOrder(['Цены ингредиентов на 12.11.2026', '40,00 ₽'])
            ->assertSee('250,00 ₽')->assertSee('200,00 ₽');
        $this->actingAs($admin)->get('/admin/analytics/csv?from=2026-11-10&to=2026-11-12')->assertOk()->assertDownload();
        $this->actingAs($admin)->get('/admin/report?from=2026-11-10&to=2026-11-12')->assertOk()->assertSee('250,00 ₽')->assertSee('200,00 ₽');
        $response = $this->actingAs($admin)->get('/admin/report/xlsx?from=2026-11-10&to=2026-11-12')->assertOk()->assertDownload();
        $this->assertInstanceOf(BinaryFileResponse::class, $response->baseResponse);
        $path = $response->baseResponse->getFile()->getPathname();
        $archive = new \PharData($path);
        $this->assertStringContainsString('250', $archive['xl/worksheets/sheet4.xml']->getContent());
        unset($archive);
        unlink($path);
    }
}
