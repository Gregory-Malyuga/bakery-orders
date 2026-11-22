<?php

namespace Domains\StoreAccount\Tests\Feature;

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

class SharedAccountPricingTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function testSharedAccountUsesFirstStoresPricesForBothStores(): void
    {
        $first = Bakery::query()->create(['name' => 'Колпино 1', 'address' => '1', 'is_active' => true]);
        $second = Bakery::query()->create(['name' => 'Колпино 2', 'address' => '2', 'is_active' => true]);
        $product = Product::query()->create(['name' => 'Пирожок', 'sort_order' => 1, 'minimum_quantity' => 1, 'packaging_mode' => 'single', 'is_active' => true]);
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
