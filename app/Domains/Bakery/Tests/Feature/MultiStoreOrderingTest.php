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

class MultiStoreOrderingTest extends TestCase
{
    use DatabaseTransactions;

    public function test_account_can_order_for_assigned_stores_only_and_comment_reaches_invoice(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-10 12:00:00', 'Europe/Moscow'));
        $first = Bakery::query()->create(['name' => 'Колпино, Седова', 'address' => 'Седова', 'is_active' => true]);
        $second = Bakery::query()->create(['name' => 'Колпино, Финляндская', 'address' => 'Финляндская', 'is_active' => true]);
        $other = Bakery::query()->create(['name' => 'Балтийская', 'address' => 'Балтийская', 'is_active' => true]);
        $product = Product::query()->create(['name' => 'Пирожок', 'sort_order' => 1, 'minimum_quantity' => 1, 'packaging_mode' => 'single', 'is_active' => true]);
        foreach ([$first, $second, $other] as $store) {
            Price::query()->create(['bakery_id' => $store->id, 'product_id' => $product->id, 'kopecks' => 3000]);
        }
        $user = User::factory()->create(['role' => UserRole::User, 'status' => UserStatus::Verify, 'bakery_id' => $first->id]);
        DB::table('bakery_account_stores')->insert([
            ['user_id' => $user->id, 'bakery_id' => $first->id],
            ['user_id' => $user->id, 'bakery_id' => $second->id],
        ]);

        $this->actingAs($user)->get('/orders?store='.$second->id)->assertOk()->assertSee('Колпино, Финляндская');
        $this->actingAs($user)->get('/orders?store='.$other->id)->assertForbidden();
        $this->actingAs($user)->post('/orders/preview', ['store' => $other->id, 'request_key' => (string) Str::uuid(), 'quantities' => [$product->id => 1]])->assertForbidden();

        $service = app(OrderingService::class);
        $quote = $service->quote($second->id, [$product->id => 2]);
        $order = $this->actingAs($user)->post('/orders', [
            'store' => $second->id,
            'request_key' => (string) Str::uuid(),
            'quantities' => [$product->id => 2],
            'quote_hash' => $quote['hash'],
            'comment' => 'Обмен: один пирожок с капустой',
        ])->assertRedirect()->assertSessionHas('success');
        $location = $order->headers->get('Location');
        $this->assertNotNull($location);
        $orderId = (int) basename($location);
        $this->assertDatabaseHas('bakery_orders', ['id' => $orderId, 'bakery_id' => $second->id, 'comment' => 'Обмен: один пирожок с капустой']);
        $this->actingAs($user)->get('/orders/'.$orderId)->assertOk()->assertSee('Обмен: один пирожок с капустой');
        $outsider = User::factory()->create(['role' => UserRole::User, 'status' => UserStatus::Verify, 'bakery_id' => $other->id]);
        $this->actingAs($outsider)->get('/orders/'.$orderId)->assertNotFound();

        $admin = User::factory()->create(['role' => UserRole::Admin, 'status' => UserStatus::Verify]);
        $this->actingAs($admin)->get('/admin/orders/'.$orderId.'/print')->assertOk()->assertSee('Обмен: один пирожок с капустой');
        $download = $this->actingAs($admin)->get('/admin/orders/'.$orderId.'/xlsx')->assertOk();
        $this->assertInstanceOf(BinaryFileResponse::class, $download->baseResponse);
        $path = $download->baseResponse->getFile()->getPathname();
        $archive = new PharData($path);
        $this->assertStringContainsString('Обмен: один пирожок с капустой', $archive['xl/worksheets/sheet1.xml']->getContent());
        unset($archive);
        unlink($path);
    }

    public function test_admin_can_assign_multiple_stores_to_one_account(): void
    {
        $first = Bakery::query()->create(['name' => 'Балтийская 26', 'address' => '26', 'is_active' => true]);
        $second = Bakery::query()->create(['name' => 'Балтийская 35', 'address' => '35', 'is_active' => true]);
        $admin = User::factory()->create(['role' => UserRole::Admin, 'status' => UserStatus::Verify]);

        $this->actingAs($admin)->post('/admin/accounts', [
            'stores' => [$first->id, $second->id],
            'name' => 'Балтийская',
            'email' => 'baltiyskaya@example.test',
            'password' => 'StrongPassword123!',
            'is_active' => 1,
        ])->assertRedirect()->assertSessionHas('success');

        $account = User::query()->where('email', 'baltiyskaya@example.test')->firstOrFail();
        $this->assertSame([$first->id, $second->id], $account->accessibleStoreIds());
        $this->actingAs($admin)->get('/admin/catalog?tab=accounts')->assertOk()
            ->assertSee('Балтийская 26, Балтийская 35')
            ->assertSee('Изменить привязку')
            ->assertSee('type="checkbox" name="stores[]" value="'.$second->id.'" checked', false)
            ->assertSee('form="account-'.$account->id.'"', false);
        $this->actingAs($account)->get('/orders?store='.$second->id)->assertOk();

        $this->actingAs($admin)->post('/admin/accounts', [
            'id' => $account->id,
            'stores' => [$second->id],
            'name' => 'Балтийская',
            'email' => 'baltiyskaya@example.test',
            'is_active' => 1,
        ])->assertRedirect();
        $account->refresh();
        $this->assertSame([$second->id], $account->accessibleStoreIds());
        $this->actingAs($account)->get('/orders?store='.$first->id)->assertForbidden();
        $account->update(['bakery_id' => $first->id]);
        $this->assertSame([$second->id], $account->accessibleStoreIds());
        $this->actingAs($account)->get('/orders?store='.$first->id)->assertForbidden();
        $this->actingAs($account)->get('/orders?store='.$second->id)->assertOk();
    }

    public function test_one_account_can_place_one_order_per_store_for_the_same_delivery_date(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-10 12:00:00', 'Europe/Moscow'));
        $first = Bakery::query()->create(['name' => 'Тестовый магазин А', 'address' => 'Адрес А', 'is_active' => true]);
        $second = Bakery::query()->create(['name' => 'Тестовый магазин Б', 'address' => 'Адрес Б', 'is_active' => true]);
        $product = Product::query()->create(['name' => 'Тестовый пирожок', 'sort_order' => 1, 'minimum_quantity' => 1, 'packaging_mode' => 'single', 'is_active' => true]);
        foreach ([$first, $second] as $store) {
            Price::query()->create(['bakery_id' => $store->id, 'product_id' => $product->id, 'kopecks' => 3000]);
        }
        $account = User::factory()->create(['role' => UserRole::User, 'status' => UserStatus::Verify, 'bakery_id' => $first->id]);
        DB::table('bakery_account_stores')->insert([
            ['user_id' => $account->id, 'bakery_id' => $first->id],
            ['user_id' => $account->id, 'bakery_id' => $second->id],
        ]);

        $quote = app(OrderingService::class)->quote($first->id, [$product->id => 1]);
        $firstResponse = $this->actingAs($account)->post('/orders', [
            'store' => $first->id, 'request_key' => (string) Str::uuid(),
            'quantities' => [$product->id => 1], 'quote_hash' => $quote['hash'],
        ])->assertRedirect();
        $this->actingAs($account)->get('/orders?store='.$first->id)
            ->assertOk()->assertSee('Для магазина «Тестовый магазин А» заказ на завтра уже оформлен')
            ->assertSee('/orders?store='.$second->id, false);
        $this->actingAs($account)->get('/orders?store='.$second->id)
            ->assertOk()->assertSee('Проверить заказ');

        $secondResponse = $this->actingAs($account)->post('/orders', [
            'store' => $second->id, 'request_key' => (string) Str::uuid(),
            'quantities' => [$product->id => 1], 'quote_hash' => $quote['hash'],
        ])->assertRedirect();
        $this->assertNotSame($firstResponse->headers->get('Location'), $secondResponse->headers->get('Location'));
        $this->assertSame(2, DB::table('bakery_orders')->whereIn('bakery_id', [$first->id, $second->id])
            ->where('delivery_date', $quote['date'])->where('status', 'accepted')->count());

        $duplicate = $this->actingAs($account)->post('/orders', [
            'store' => $first->id, 'request_key' => (string) Str::uuid(),
            'quantities' => [$product->id => 1], 'quote_hash' => $quote['hash'],
        ])->assertRedirect();
        $this->assertSame($firstResponse->headers->get('Location'), $duplicate->headers->get('Location'));
        $this->assertSame(2, DB::table('bakery_orders')->whereIn('bakery_id', [$first->id, $second->id])
            ->where('delivery_date', $quote['date'])->where('status', 'accepted')->count());
        Carbon::setTestNow();
    }

    public function test_store_can_be_unassigned_and_then_assigned_to_another_account(): void
    {
        $store = Bakery::query()->create(['name' => 'Тестовый свободный магазин', 'address' => 'Адрес', 'is_active' => true]);
        $admin = User::factory()->create(['role' => UserRole::Admin, 'status' => UserStatus::Verify]);
        $first = User::factory()->create(['role' => UserRole::User, 'status' => UserStatus::Verify, 'bakery_id' => $store->id]);
        DB::table('bakery_account_stores')->insert(['user_id' => $first->id, 'bakery_id' => $store->id]);

        $this->actingAs($admin)->post('/admin/accounts', [
            'stores' => [$store->id], 'name' => 'Второй аккаунт', 'email' => 'second-store@example.test',
            'password' => 'StrongPassword123!', 'is_active' => 1,
        ])->assertSessionHasErrors('stores');
        $this->assertDatabaseMissing('users', ['email' => 'second-store@example.test']);

        $this->actingAs($admin)->post('/admin/accounts', [
            'id' => $first->id, 'name' => $first->name, 'email' => $first->email, 'is_active' => 1,
        ])->assertRedirect()->assertSessionHas('success');
        $first->refresh();
        $this->assertNull($first->bakery_id);
        $this->assertSame([], $first->accessibleStoreIds());
        $this->actingAs($first)->get('/orders')->assertOk()->assertSee('Нет назначенных магазинов');
        $this->actingAs($first)->get('/orders?store='.$store->id)->assertForbidden();
        $this->actingAs($admin)->get('/admin/catalog?tab=accounts')->assertOk()->assertSee('Нет магазинов');

        $this->actingAs($admin)->post('/admin/accounts', [
            'stores' => [$store->id], 'name' => 'Второй аккаунт', 'email' => 'second-store@example.test',
            'password' => 'StrongPassword123!', 'is_active' => 1,
        ])->assertRedirect()->assertSessionHas('success');
        $second = User::query()->where('email', 'second-store@example.test')->firstOrFail();
        $this->assertSame([$store->id], $second->accessibleStoreIds());
        $this->assertDatabaseHas('bakery_account_stores', ['user_id' => $second->id, 'bakery_id' => $store->id]);
        $this->actingAs($second)->get('/orders')->assertOk();
    }

    public function test_shared_price_does_not_force_the_same_assortment_on_every_store(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-10 12:00:00', 'Europe/Moscow'));
        $first = Bakery::query()->create(['name' => 'Тестовый магазин 1', 'address' => 'Адрес 1', 'is_active' => true]);
        $second = Bakery::query()->create(['name' => 'Тестовый магазин 2', 'address' => 'Адрес 2', 'is_active' => true]);
        DB::table('bakeries')->where('id', $second->id)->update(['price_source_id' => $first->id]);
        $product = Product::query()->create(['name' => 'Тестовый товар', 'sort_order' => 1, 'minimum_quantity' => 1, 'packaging_mode' => 'single', 'is_active' => true]);
        Price::query()->create(['bakery_id' => $first->id, 'product_id' => $product->id, 'kopecks' => 3000]);
        $account = User::factory()->create(['role' => UserRole::User, 'status' => UserStatus::Verify, 'bakery_id' => $first->id]);
        DB::table('bakery_account_stores')->insert([
            ['user_id' => $account->id, 'bakery_id' => $first->id],
            ['user_id' => $account->id, 'bakery_id' => $second->id],
        ]);
        $admin = User::factory()->create(['role' => UserRole::Admin, 'status' => UserStatus::Verify]);

        $this->actingAs($admin)->post('/admin/availability', [
            'store' => $second->id, 'product' => $product->id, 'is_available' => 0,
        ])->assertRedirect()->assertSessionHas('success');
        $this->assertDatabaseHas('bakery_store_product_exclusions', ['bakery_id' => $second->id, 'product_id' => $product->id]);
        $this->actingAs($account)->get('/orders?store='.$second->id)->assertOk()
            ->assertSee('Нет цены или товар не поставляется — заказать нельзя');
        $this->actingAs($account)->post('/orders/preview', [
            'store' => $second->id, 'request_key' => (string) Str::uuid(), 'quantities' => [$product->id => 1],
        ])->assertSessionHasErrors('quantities');
        $this->actingAs($account)->post('/orders/preview', [
            'store' => $first->id, 'request_key' => (string) Str::uuid(), 'quantities' => [$product->id => 1],
        ])->assertOk();
        $this->actingAs($admin)->get('/admin/catalog?tab=prices&store='.$second->id)->assertOk()
            ->assertSee('30,00');

        $this->actingAs($admin)->post('/admin/availability', [
            'store' => $second->id, 'product' => $product->id, 'is_available' => 1,
        ])->assertRedirect();
        $this->assertDatabaseMissing('bakery_store_product_exclusions', ['bakery_id' => $second->id, 'product_id' => $product->id]);
        $this->actingAs($account)->post('/orders/preview', [
            'store' => $second->id, 'request_key' => (string) Str::uuid(), 'quantities' => [$product->id => 1],
        ])->assertOk();
        Carbon::setTestNow();
    }

    public function test_admin_tabs_show_only_selected_section_and_keep_date(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'status' => UserStatus::Verify]);
        $date = '2026-10-11';

        $this->actingAs($admin)->get('/admin?date='.$date.'&tab=orders')
            ->assertOk()->assertSee('Принятые заказы')->assertDontSee('Производственная сводка')
            ->assertSee('tab=production')->assertSee('value="'.$date.'"', false);
        $this->actingAs($admin)->get('/admin?date='.$date.'&tab=production')
            ->assertOk()->assertSee('Производственная сводка')->assertDontSee('Принятые заказы');
        $this->actingAs($admin)->get('/admin?date='.$date.'&tab=packaging')
            ->assertOk()->assertSee('Остаток, шт.')->assertDontSee('Заготовки по рецептурам');
        $this->actingAs($admin)->get('/admin?date='.$date.'&tab=prep')
            ->assertOk()->assertSee('Заготовки по рецептурам')->assertDontSee('Остаток, шт.');
        $this->actingAs($admin)->get('/admin/catalog?tab=accounts')
            ->assertOk()->assertSee('Аккаунты магазинов')->assertDontSee('Персональные цены');
        $this->actingAs($admin)->get('/admin/catalog?tab=prices')
            ->assertOk()->assertSee('Персональные цены')->assertDontSee('Аккаунты магазинов');

        for ($number = 1; $number <= 21; $number++) {
            Bakery::query()->create(['name' => sprintf('Магазин %02d', $number), 'address' => 'Тест', 'is_active' => true]);
        }
        $this->actingAs($admin)->get('/admin/catalog?tab=stores')
            ->assertOk()->assertSee('Магазин 01')->assertDontSee('Магазин 21')->assertSee('page=2');
        $this->actingAs($admin)->get('/admin/catalog?tab=stores&page=2')
            ->assertOk()->assertSee('Магазин 21')->assertDontSee('Магазин 01');
    }
}
