<?php

namespace Domains\Bakery\Tests\Feature;

use Domains\Bakery\Application\OrderingService;
use Domains\Bakery\Application\PeriodReportService;
use Domains\Bakery\Application\ProductionCalculator;
use Domains\Bakery\Domain\Models\Bakery;
use Domains\Bakery\Domain\Models\Price;
use Domains\Bakery\Domain\Models\Product;
use Domains\User\Domain\Enums\UserRole;
use Domains\User\Domain\Enums\UserStatus;
use Domains\User\Domain\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class RecipeEditingTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_admin_edits_product_and_dough_norms_used_in_prep_and_report_and_can_reset(): void
    {
        $this->withoutMiddleware(PreventRequestForgery::class);
        Carbon::setTestNow(Carbon::parse('2026-10-17 12:00:00', 'Europe/Moscow'));
        $store = Bakery::query()->create(['name' => 'Рецептурная точка', 'address' => 'Адрес', 'is_active' => true]);
        $product = Product::query()->create(['name' => 'Пицца', 'sort_order' => 1, 'minimum_quantity' => 1, 'packaging_mode' => 'single', 'is_active' => true]);
        Price::query()->create(['bakery_id' => $store->id, 'product_id' => $product->id, 'kopecks' => 3000]);
        $admin = User::factory()->create(['role' => UserRole::Admin, 'status' => UserStatus::Verify]);
        $customer = User::factory()->create(['role' => UserRole::User, 'status' => UserStatus::Verify, 'bakery_id' => $store->id]);
        $service = app(OrderingService::class);
        $quote = $service->quote($store->id, [$product->id => 2]);
        $service->place($store->id, (string) Str::uuid(), [$product->id => 2], $quote['hash']);

        $this->actingAs($customer)->get('/admin/recipes')->assertForbidden();
        $this->actingAs($admin)->get('/admin/recipes?product='.$product->id)->assertOk()->assertSee('Помидоры');
        $this->actingAs($admin)->post('/admin/recipes/product', [
            'product' => $product->id, 'dough_grams' => 150,
            'ingredients' => [
                ['name' => 'Помидоры', 'amount' => 50, 'unit' => 'g'],
                ['name' => 'Яйцо', 'amount' => 0.5, 'unit' => 'pcs'],
            ],
        ])->assertRedirect('/admin/recipes?product='.$product->id);
        $this->actingAs($admin)->post('/admin/recipes/dough', [
            'kind' => 'main_dough', 'batch_size' => 1000,
            'ingredients' => [['name' => 'Мука', 'amount' => 600]],
        ])->assertRedirect();

        $production = $this->actingAs($admin)->get('/admin?date='.$quote['date'].'&tab=prep')->assertOk()
            ->assertSee('0,100 кг')->assertSee('1,000 шт.')->viewData('production');
        $this->assertEqualsWithDelta(300, $production['dough_grams'], 0.001);
        $this->assertEqualsWithDelta(100, $production['ingredients']['Помидоры'], 0.001);
        $this->assertEqualsWithDelta(180, $production['main_dough']['Мука'], 0.001);
        $this->assertEqualsWithDelta(1, $production['recipes']['Пицца']['other_ingredients']['Яйцо']['quantity'], 0.001);

        $period = app(PeriodReportService::class)->build($quote['date'], $quote['date']);
        $this->assertEqualsWithDelta(100, $period['production']['ingredients']['Помидоры'], 0.001);
        $this->assertEqualsWithDelta(1, $period['production']['other_ingredients']['шт.']['Яйцо'], 0.001);
        $this->actingAs($admin)->get('/admin/report?from='.$quote['date'].'&to='.$quote['date'])->assertOk()->assertSee('<td>Яйцо</td><td>1,000</td><td>шт.</td>', false);
        $this->actingAs($admin)->post('/admin/recipes/reset', ['kind' => 'product', 'product' => $product->id])->assertRedirect();
        $this->assertSame(80, app(ProductionCalculator::class)->calculate(['Пицца' => 2], ['Пицца' => $product->id])['ingredients']['Помидоры']);
    }
}
