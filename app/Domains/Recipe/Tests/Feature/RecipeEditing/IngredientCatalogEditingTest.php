<?php

namespace Domains\Recipe\Tests\Feature\RecipeEditing;

use Domains\Bakery\Application\OrderingService;
use Domains\Bakery\Application\ProductionCalculator;
use Domains\Bakery\Domain\Models\Bakery;
use Domains\Bakery\Domain\Models\Price;
use Domains\Bakery\Domain\Models\Product;
use Domains\Recipe\Tests\Feature\Support\RecipeEditingCase;
use Domains\User\Domain\Enums\UserRole;
use Domains\User\Domain\Enums\UserStatus;
use Domains\User\Domain\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PharData;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class IngredientCatalogEditingTest extends RecipeEditingCase
{
    public function testIngredientCatalogSelectionAndRenameReachPrepReportAndExcel(): void
    {
        $this->withoutMiddleware(PreventRequestForgery::class);
        Carbon::setTestNow(Carbon::parse('2026-10-17 12:00:00', 'Europe/Moscow'));
        $admin = User::factory()->create(['role' => UserRole::Admin, 'status' => UserStatus::Verify]);
        $store = Bakery::query()->create(['name' => 'Точка справочника', 'address' => 'Адрес', 'is_active' => true]);
        $product = Product::query()->create(['name' => 'Пицца', 'sort_order' => 1, 'minimum_quantity' => 1, 'packaging_mode' => 'single', 'is_active' => true]);
        Price::query()->create(['bakery_id' => $store->id, 'product_id' => $product->id, 'kopecks' => 3000]);
        $service = app(OrderingService::class);
        $quote = $service->quote($store->id, [$product->id => 2]);
        $service->place($store->id, (string) Str::uuid(), [$product->id => 2], $quote['hash']);

        $this->actingAs($admin)->get('/admin/ingredients')->assertOk()->assertSee('Яйцо');
        $this->actingAs($admin)->get('/admin/recipes?product='.$product->id)->assertOk()
            ->assertSee('name="ingredients[0][ingredient_id]"', false);
        $this->actingAs($admin)->get('/admin/recipes?section=dough')->assertOk()
            ->assertSee('name="ingredients[0][ingredient_id]"', false);
        $this->actingAs($admin)->post('/admin/ingredients', ['name' => 'Паприка', 'unit' => 'g'])->assertRedirect();
        $this->actingAs($admin)->post('/admin/ingredients', ['name' => 'паприка', 'unit' => 'g'])
            ->assertSessionHasErrors('name');
        $paprika = DB::table('bakery_ingredients')->where('name', 'Паприка')->first();
        $flour = DB::table('bakery_ingredients')->where('name', 'Мука')->where('unit', 'g')->first();
        $eggPieces = DB::table('bakery_ingredients')->where('name', 'Яйцо')->where('unit', 'pcs')->first();
        $this->assertNotNull($paprika);
        $this->assertNotNull($flour);
        $this->assertNotNull($eggPieces);
        $paprikaId = $paprika->id;
        $flourId = $flour->id;
        $eggPiecesId = $eggPieces->id;
        $this->assertIsInt($paprikaId);
        $this->assertIsInt($flourId);
        $this->assertIsInt($eggPiecesId);

        $this->actingAs($admin)->post('/admin/recipes/dough', [
            'kind' => 'main_dough', 'batch_size' => 1000,
            'ingredients' => [['ingredient_id' => 999999, 'amount' => 10]],
        ])->assertSessionHasErrors('ingredients');
        $this->actingAs($admin)->post('/admin/recipes/dough', [
            'kind' => 'main_dough', 'batch_size' => 1000,
            'ingredients' => [['ingredient_id' => $eggPiecesId, 'amount' => 10]],
        ])->assertSessionHasErrors('ingredients');
        $this->actingAs($admin)->post('/admin/recipes/dough', [
            'kind' => 'main_dough', 'batch_size' => 1000,
            'ingredients' => [['ingredient_id' => $flourId, 'amount' => 600]],
        ])->assertRedirect();

        $this->actingAs($admin)->post('/admin/recipes/product', [
            'product' => $product->id, 'dough_type' => 'main', 'dough_grams' => 136,
            'ingredients' => [['ingredient_id' => $paprikaId, 'amount' => 3]],
        ])->assertRedirect();
        $this->actingAs($admin)->post('/admin/ingredients/'.$paprikaId.'/rename', ['name' => 'Паприка молотая'])->assertRedirect();
        $this->actingAs($admin)->post('/admin/ingredients/'.$flourId.'/rename', ['name' => 'Мука хлебопекарная'])->assertRedirect();
        $this->actingAs($admin)->post('/admin/ingredients/'.$paprikaId.'/toggle')->assertRedirect();

        $production = app(ProductionCalculator::class)->calculate(['Пицца' => 2], ['Пицца' => $product->id]);
        $this->assertEqualsWithDelta(6, $production['ingredients']['Паприка молотая'], 0.001);
        $this->assertArrayHasKey('Мука хлебопекарная', $production['main_dough']);
        $this->assertEqualsWithDelta(163.2, $production['main_dough']['Мука хлебопекарная'], 0.001);
        $this->assertArrayHasKey('Мука хлебопекарная', $production['samsa_dough']);
        $this->actingAs($admin)->get('/admin/recipes?product='.$product->id)->assertOk()
            ->assertSee('Паприка молотая')->assertSee('скрыт');
        $this->actingAs($admin)->get('/admin?date='.$quote['date'].'&tab=prep')->assertOk()
            ->assertSee('Паприка молотая')->assertSee('Мука хлебопекарная');
        $this->actingAs($admin)->get('/admin/report?from='.$quote['date'].'&to='.$quote['date'])->assertOk()
            ->assertSee('Паприка молотая')->assertSee('Мука хлебопекарная');
        $response = $this->actingAs($admin)->get('/admin/report/xlsx?from='.$quote['date'].'&to='.$quote['date'])->assertOk();
        $this->assertInstanceOf(BinaryFileResponse::class, $response->baseResponse);
        $path = $response->baseResponse->getFile()->getPathname();
        $archive = new PharData($path);
        $sheet = $archive['xl/worksheets/sheet3.xml']->getContent();
        $this->assertStringContainsString('Паприка молотая', $sheet);
        $this->assertStringContainsString('Мука хлебопекарная', $sheet);
        unset($archive);
        unlink($path);
    }
}
