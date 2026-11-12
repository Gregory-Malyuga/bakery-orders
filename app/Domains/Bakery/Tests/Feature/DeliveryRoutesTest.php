<?php

namespace Domains\Bakery\Tests\Feature;

use Domains\Bakery\Domain\Models\Bakery;
use Domains\Bakery\Domain\Models\Order;
use Domains\Bakery\Domain\Models\Product;
use Domains\User\Domain\Enums\UserRole;
use Domains\User\Domain\Enums\UserStatus;
use Domains\User\Domain\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DeliveryRoutesTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_template_order_and_saved_sheet_are_stable_after_template_change(): void
    {
        $this->withoutMiddleware(PreventRequestForgery::class);
        Carbon::setTestNow(Carbon::parse('2026-11-11 19:00:00', 'Europe/Moscow'));
        $admin = User::factory()->create(['role' => UserRole::Admin, 'status' => UserStatus::Verify]);
        $a = Bakery::query()->create(['name' => 'Точка А', 'address' => 'Адрес А', 'is_active' => true]);
        $b = Bakery::query()->create(['name' => 'Точка Б', 'address' => 'Адрес Б', 'is_active' => true]);

        $this->actingAs($admin)->post('/admin/delivery-routes', ['name' => 'Маршрут', 'driver' => 'Водитель', 'stores' => [$b->id, $a->id]])->assertRedirect();
        $this->actingAs($admin)->get('/admin/delivery-routes?date=2026-11-12')->assertOk()->assertSee('Маршрут');
        /** @var object{id:int,store_ids:string}|null $route */
        $route = DB::table('bakery_delivery_routes')->where('name', 'Маршрут')->first();
        $this->assertNotNull($route);
        $this->assertSame([$b->id, $a->id], json_decode($route->store_ids, true));
        $this->actingAs($admin)->post('/admin/delivery-sheets', ['date' => '2026-11-12', 'route_id' => $route->id, 'save' => '1'])->assertRedirect();
        /** @var object{id:int}|null $saved */
        $saved = DB::table('bakery_delivery_sheets')->first();
        $this->assertNotNull($saved);
        $this->actingAs($admin)->get('/admin/delivery-sheets/'.$saved->id)->assertOk()->assertSeeInOrder(['Точка Б', 'Точка А'])->assertSee('Нет заказа');

        $this->actingAs($admin)->post('/admin/delivery-routes', ['id' => $route->id, 'name' => 'Новый маршрут', 'driver' => 'Другой водитель', 'stores' => [$a->id]])->assertRedirect();
        $this->actingAs($admin)->get('/admin/delivery-sheets/'.$saved->id)->assertOk()->assertSee('Водитель')->assertSeeInOrder(['Точка Б', 'Точка А']);
        $this->actingAs($admin)->post('/admin/delivery-sheets', ['date' => '2026-11-12', 'driver' => 'Другой', 'stores' => [$b->id]])->assertSessionHasErrors('stores');
    }

    public function test_sheet_cannot_be_printed_before_cutoff(): void
    {
        $this->withoutMiddleware(PreventRequestForgery::class);
        Carbon::setTestNow(Carbon::parse('2026-11-11 18:00:00', 'Europe/Moscow'));
        $admin = User::factory()->create(['role' => UserRole::Admin, 'status' => UserStatus::Verify]);
        $store = Bakery::query()->create(['name' => 'Точка', 'address' => 'Адрес', 'is_active' => true]);
        $this->actingAs($admin)->post('/admin/delivery-sheets', ['date' => '2026-11-12', 'driver' => 'Водитель', 'stores' => [$store->id]])->assertSessionHasErrors('date');
    }

    public function test_printed_amount_shows_order_return_and_amount_to_collect(): void
    {
        $this->withoutMiddleware(PreventRequestForgery::class);
        Carbon::setTestNow(Carbon::parse('2026-11-11 19:00:00', 'Europe/Moscow'));
        $admin = User::factory()->create(['role' => UserRole::Admin, 'status' => UserStatus::Verify]);
        $store = Bakery::query()->create(['name' => 'Точка', 'address' => 'Адрес', 'is_active' => true]);
        $product = Product::query()->create(['name' => 'Хлеб', 'sort_order' => 1, 'minimum_quantity' => 1, 'packaging_mode' => 'single', 'is_active' => true]);
        $order = Order::query()->create(['bakery_id' => $store->id, 'delivery_date' => '2026-11-12', 'status' => 'accepted', 'active_slot' => 1, 'request_key' => (string) Str::uuid(), 'store_name' => 'Точка', 'store_address' => 'Адрес', 'total_kopecks' => 100000]);
        DB::table('bakery_order_items')->insert(['order_id' => $order->id, 'product_id' => $product->id, 'product_name' => 'Хлеб', 'quantity' => 10, 'unit_kopecks' => 10000, 'total_kopecks' => 100000]);
        DB::table('bakery_returns')->insert(['order_id' => $order->id, 'product_id' => $product->id, 'admin_user_id' => $admin->id, 'quantity' => 2, 'unit_kopecks' => 10000, 'product_name' => 'Хлеб', 'is_order_item' => true, 'status' => 'active']);

        $this->actingAs($admin)->post('/admin/delivery-sheets', ['date' => '2026-11-12', 'driver' => 'Водитель', 'stores' => [$store->id]])
            ->assertOk()->assertSee('1 000,00')->assertSee('200,00')->assertSee('800,00');
        $this->assertSame(0, DB::table('bakery_delivery_sheets')->count());
    }
}
