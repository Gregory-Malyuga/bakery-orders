<?php

namespace Domains\StockPrice\Tests\Feature;

use Carbon\Carbon;
use Domains\User\Domain\Enums\UserRole;
use Domains\User\Domain\Enums\UserStatus;
use Domains\User\Domain\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class StockPriceHistoryTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function testStockPriceMeasureStartsTomorrowAndKeepsDailyHistory(): void
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
