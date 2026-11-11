<?php

namespace Domains\Bakery\Tests\Feature;

use Domains\Bakery\Domain\Models\Bakery;
use Domains\Bakery\Domain\Models\Price;
use Domains\Bakery\Domain\Models\Product;
use Domains\User\Domain\Enums\UserRole;
use Domains\User\Domain\Enums\UserStatus;
use Domains\User\Domain\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BulkProductPricesTest extends TestCase
{
    use DatabaseTransactions;

    public function test_shared_price_requires_all_linked_stores_and_store_user_cannot_access_card(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'status' => UserStatus::Verify]);
        $first = Bakery::query()->create(['name' => 'Общий 1', 'address' => '1', 'is_active' => true]);
        $second = Bakery::query()->create(['name' => 'Общий 2', 'address' => '2', 'is_active' => true]);
        DB::table('bakeries')->where('id', $second->id)->update(['price_source_id' => $first->id]);
        $product = Product::query()->create(['name' => 'Общий товар', 'sort_order' => 1, 'minimum_quantity' => 1, 'packaging_mode' => 'single', 'is_active' => true]);
        Price::query()->create(['bakery_id' => $first->id, 'product_id' => $product->id, 'kopecks' => 500]);
        $user = User::factory()->create(['role' => UserRole::User, 'status' => UserStatus::Verify, 'bakery_id' => $first->id]);
        $url = '/admin/products/'.$product->id;
        $this->actingAs($user)->get($url)->assertForbidden();
        $this->actingAs($admin)->post($url.'/prices', ['stores' => [$first->id], 'direction' => 'increase', 'rubles' => '1', 'request_key' => (string) Str::uuid()])->assertSessionHasErrors('stores');
        $this->assertDatabaseHas('bakery_prices', ['bakery_id' => $first->id, 'kopecks' => 500]);
        $this->actingAs($admin)->post($url.'/prices', ['stores' => [$first->id, $second->id], 'direction' => 'increase', 'rubles' => '1', 'request_key' => (string) Str::uuid()])->assertSessionHas('success');
        $this->assertDatabaseHas('bakery_prices', ['bakery_id' => $first->id, 'kopecks' => 600]);
        $this->assertSame(1, DB::table('bakery_prices')->where('product_id', $product->id)->count());
    }

    public function test_bulk_price_is_atomic_idempotent_and_reversible_until_another_edit(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'status' => UserStatus::Verify]);
        $first = Bakery::query()->create(['name' => 'Первая', 'address' => '1', 'is_active' => true]);
        $second = Bakery::query()->create(['name' => 'Вторая', 'address' => '2', 'is_active' => true]);
        $third = Bakery::query()->create(['name' => 'Третья', 'address' => '3', 'is_active' => true]);
        $product = Product::query()->create(['name' => 'Товар', 'sort_order' => 1, 'minimum_quantity' => 1, 'packaging_mode' => 'single', 'is_active' => true]);
        foreach ([[$first, 1000], [$second, 100], [$third, 500]] as [$store, $amount]) {
            Price::query()->create(['bakery_id' => $store->id, 'product_id' => $product->id, 'kopecks' => $amount]);
        }
        $url = '/admin/products/'.$product->id;
        $this->actingAs($admin)->get($url)->assertOk()->assertSee('Первая')->assertSee('10,00 ₽');
        $invalid = ['stores' => [$first->id, $second->id], 'direction' => 'decrease', 'rubles' => '2', 'request_key' => (string) Str::uuid()];
        $this->actingAs($admin)->post($url.'/prices', $invalid)->assertSessionHasErrors('stores');
        $this->assertDatabaseHas('bakery_prices', ['bakery_id' => $first->id, 'kopecks' => 1000]);

        $valid = ['stores' => [$first->id, $second->id], 'direction' => 'increase', 'rubles' => '2', 'request_key' => (string) Str::uuid()];
        $this->actingAs($admin)->post($url.'/prices', $valid)->assertSessionHas('success');
        $this->actingAs($admin)->post($url.'/prices', $valid)->assertSessionHas('success');
        $this->assertDatabaseHas('bakery_prices', ['bakery_id' => $first->id, 'kopecks' => 1200]);
        $this->assertDatabaseHas('bakery_prices', ['bakery_id' => $second->id, 'kopecks' => 300]);
        $this->assertDatabaseHas('bakery_prices', ['bakery_id' => $third->id, 'kopecks' => 500]);
        $operation = DB::table('bakery_price_operations')->where('request_key', $valid['request_key'])->firstOrFail();
        /** @var object{id:int} $operation */
        $this->actingAs($admin)->post($url.'/prices/'.$operation->id.'/reverse', ['reason' => 'Ошибка'])->assertSessionHas('success');
        $this->actingAs($admin)->post($url.'/prices/'.$operation->id.'/reverse', ['reason' => 'Повтор'])->assertSessionHasErrors('reason');
        $this->assertDatabaseHas('bakery_prices', ['bakery_id' => $first->id, 'kopecks' => 1000]);

        $next = ['stores' => [$first->id, $second->id], 'direction' => 'increase', 'rubles' => '2', 'request_key' => (string) Str::uuid()];
        $this->actingAs($admin)->post($url.'/prices', $next)->assertSessionHas('success');
        $this->actingAs($admin)->post('/admin/prices', ['store' => $first->id, 'product' => $product->id, 'rubles' => '13'])->assertSessionHas('success');
        $newOperation = DB::table('bakery_price_operations')->where('request_key', $next['request_key'])->firstOrFail();
        /** @var object{id:int} $newOperation */
        $this->actingAs($admin)->post($url.'/prices/'.$newOperation->id.'/reverse', ['reason' => 'Поздно'])->assertSessionHasErrors('reason');
        $this->assertDatabaseHas('bakery_prices', ['bakery_id' => $second->id, 'kopecks' => 300]);
    }
}
