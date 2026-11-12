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

    public function test_saved_sheet_can_be_copied_and_changed_without_editing_original(): void
    {
        $this->withoutMiddleware(PreventRequestForgery::class);
        Carbon::setTestNow(Carbon::parse('2026-11-11 19:00:00', 'Europe/Moscow'));
        $admin = User::factory()->create(['role' => UserRole::Admin, 'status' => UserStatus::Verify]);
        $a = Bakery::query()->create(['name' => 'Точка А', 'address' => 'Адрес А', 'is_active' => true]);
        $b = Bakery::query()->create(['name' => 'Точка Б', 'address' => 'Адрес Б', 'is_active' => true]);

        $this->actingAs($admin)->get('/admin/delivery-routes?date=2026-11-12')->assertOk()
            ->assertSee('Новый маршрутный лист')->assertSee('Сохранить лист')
            ->assertDontSee('Открыть лист без сохранения')->assertDontSee('Сохранить шаблон');
        $this->actingAs($admin)->post('/admin/delivery-sheets', [
            'date' => '2026-11-12', 'name' => 'Первый', 'driver' => 'Водитель', 'stores' => [$b->id, $a->id],
        ])->assertRedirect();
        /** @var object{id:int,snapshot:string}|null $original */
        $original = DB::table('bakery_delivery_sheets')->first();
        $this->assertNotNull($original);
        $this->actingAs($admin)->get('/admin/delivery-routes?date=2026-11-12&copy='.$original->id)->assertOk()
            ->assertSee('value="Первый"', false)->assertSee('value="Водитель"', false)
            ->assertSee('Дублировать')->assertSee('Редактировать');
        $this->actingAs($admin)->post('/admin/delivery-sheets', [
            'date' => '2026-11-12', 'name' => 'Копия', 'driver' => 'Другой водитель', 'stores' => [$a->id, $b->id],
        ])->assertRedirect();
        $this->assertSame(2, DB::table('bakery_delivery_sheets')->count());
        $this->assertSame($original->snapshot, DB::table('bakery_delivery_sheets')->where('id', $original->id)->value('snapshot'));
        $this->actingAs($admin)->get('/admin/delivery-sheets/'.$original->id)->assertOk()->assertSeeInOrder(['Точка Б', 'Точка А']);
    }

    public function test_sheet_cannot_be_saved_before_cutoff(): void
    {
        $this->withoutMiddleware(PreventRequestForgery::class);
        Carbon::setTestNow(Carbon::parse('2026-11-11 18:00:00', 'Europe/Moscow'));
        $admin = User::factory()->create(['role' => UserRole::Admin, 'status' => UserStatus::Verify]);
        $store = Bakery::query()->create(['name' => 'Точка', 'address' => 'Адрес', 'is_active' => true]);
        $this->actingAs($admin)->post('/admin/delivery-sheets', ['date' => '2026-11-12', 'name' => 'Маршрут', 'driver' => 'Водитель', 'stores' => [$store->id]])->assertSessionHasErrors('date');
        $this->actingAs($admin)->get('/admin/delivery-routes')->assertOk()->assertViewHas('date', '2026-11-11');
        $this->actingAs($admin)->post('/admin/delivery-sheets', ['date' => '2026-11-11', 'name' => 'Маршрут', 'driver' => 'Водитель', 'stores' => [$store->id]])->assertRedirect();
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

        $this->actingAs($admin)->post('/admin/delivery-sheets', ['date' => '2026-11-12', 'name' => 'Маршрут', 'driver' => 'Водитель', 'stores' => [$store->id]])->assertRedirect();
        /** @var object{id:int}|null $saved */
        $saved = DB::table('bakery_delivery_sheets')->first();
        $this->assertNotNull($saved);
        $this->actingAs($admin)->get('/admin/delivery-sheets/'.$saved->id)
            ->assertOk()->assertSee('1 000,00')->assertSee('200,00')->assertSee('800,00');
    }

    public function test_saved_sheet_can_be_edited_with_driver_pay_and_reordered_stops(): void
    {
        $this->withoutMiddleware(PreventRequestForgery::class);
        Carbon::setTestNow(Carbon::parse('2026-11-11 19:00:00', 'Europe/Moscow'));
        $admin = User::factory()->create(['role' => UserRole::Admin, 'status' => UserStatus::Verify]);
        $a = Bakery::query()->create(['name' => 'Точка А', 'address' => 'Адрес А', 'is_active' => true]);
        $b = Bakery::query()->create(['name' => 'Точка Б', 'address' => 'Адрес Б', 'is_active' => true]);

        $this->actingAs($admin)->post('/admin/delivery-sheets', [
            'date' => '2026-11-12', 'name' => 'Мой маршрут', 'driver' => 'Первый водитель', 'salary' => '3500,50',
            'stores' => [$a->id, $b->id],
        ])->assertRedirect();
        /** @var object{id:int,snapshot:string}|null $saved */
        $saved = DB::table('bakery_delivery_sheets')->first();
        $this->assertNotNull($saved);
        $this->actingAs($admin)->get('/admin/delivery-sheets/'.$saved->id)->assertOk()->assertSee('Мой маршрут')->assertSee('3 500,50 ₽');
        $this->actingAs($admin)->post('/admin/delivery-sheet-prints', ['sheet_id' => $saved->id])->assertOk()->assertSee('Состав листа сохранён в истории печати');
        /** @var object{id:int,snapshot:string}|null $printed */
        $printed = DB::table('bakery_delivery_sheet_prints')->first();
        $this->assertNotNull($printed);
        $this->actingAs($admin)->get('/admin/delivery-sheets/'.$saved->id.'/edit')->assertOk()->assertSee('Первый водитель')->assertSee('3500.50');
        $this->actingAs($admin)->post('/admin/delivery-sheets/'.$saved->id.'/edit', [
            'date' => '2026-11-12', 'name' => 'Исправленный маршрут', 'driver' => 'Второй водитель',
            'salary' => '4000', 'stores' => [$b->id, $a->id],
        ])->assertRedirect('/admin/delivery-sheets/'.$saved->id);
        $this->actingAs($admin)->get('/admin/delivery-sheets/'.$saved->id)->assertOk()
            ->assertSee('Исправленный маршрут')->assertSee('Второй водитель')->assertSee('4 000,00 ₽')
            ->assertSeeInOrder(['Точка Б', 'Точка А']);
        /** @var object{snapshot:string}|null $updated */
        $updated = DB::table('bakery_delivery_sheets')->find($saved->id);
        $this->assertNotNull($updated);
        /** @var array{salary_kopecks:int,rows:list<array{store_id:int}>} $snapshot */
        $snapshot = json_decode($updated->snapshot, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(400000, $snapshot['salary_kopecks']);
        $this->assertSame([$b->id, $a->id], array_column($snapshot['rows'], 'store_id'));
        $this->actingAs($admin)->get('/admin/delivery-sheet-prints/'.$printed->id)->assertOk()->assertSee('Мой маршрут')->assertSee('Только просмотр.')->assertDontSee('Редактировать');
        $this->assertSame($printed->snapshot, DB::table('bakery_delivery_sheet_prints')->where('id', $printed->id)->value('snapshot'));
        $this->actingAs($admin)->post('/admin/delivery-sheets/'.$saved->id.'/edit', [
            'date' => '2026-11-12', 'name' => 'Исправленный маршрут', 'driver' => 'Второй водитель',
            'salary' => '-1', 'stores' => [$a->id],
        ])->assertSessionHasErrors('salary');
    }

    public function test_only_saved_sheet_can_be_printed_and_archived(): void
    {
        $this->withoutMiddleware(PreventRequestForgery::class);
        Carbon::setTestNow(Carbon::parse('2026-11-11 19:00:00', 'Europe/Moscow'));
        $admin = User::factory()->create(['role' => UserRole::Admin, 'status' => UserStatus::Verify]);
        $store = Bakery::query()->create(['name' => 'Точка', 'address' => 'Адрес', 'is_active' => true]);
        $this->actingAs($admin)->post('/admin/delivery-sheets', [
            'date' => '2026-11-12', 'name' => 'Маршрут', 'driver' => 'Водитель', 'stores' => [$store->id],
        ])->assertRedirect();
        $this->assertSame(0, DB::table('bakery_delivery_sheet_prints')->count());
        /** @var object{id:int,snapshot:string}|null $saved */
        $saved = DB::table('bakery_delivery_sheets')->first();
        $this->assertNotNull($saved);
        $this->actingAs($admin)->post('/admin/delivery-sheet-prints', [])->assertSessionHasErrors('sheet_id');
        $this->actingAs($admin)->post('/admin/delivery-sheet-prints', ['sheet_id' => $saved->id])->assertOk()->assertSee('Состав листа сохранён в истории печати');
        /** @var object{snapshot:string,source_sheet_id:int}|null $printed */
        $printed = DB::table('bakery_delivery_sheet_prints')->first();
        $this->assertNotNull($printed);
        $this->assertSame($saved->id, $printed->source_sheet_id);
        $this->assertSame($saved->snapshot, $printed->snapshot);
        $this->assertSame(1, DB::table('bakery_delivery_sheet_prints')->count());
    }

    public function test_printing_overlapping_sheets_and_deleting_one_keeps_print_history(): void
    {
        $this->withoutMiddleware(PreventRequestForgery::class);
        Carbon::setTestNow(Carbon::parse('2026-11-11 19:00:00', 'Europe/Moscow'));
        $admin = User::factory()->create(['role' => UserRole::Admin, 'status' => UserStatus::Verify]);
        $store = Bakery::query()->create(['name' => 'Точка А', 'address' => 'Адрес А', 'is_active' => true]);
        $other = Bakery::query()->create(['name' => 'Точка Б', 'address' => 'Адрес Б', 'is_active' => true]);

        foreach (['Первый', 'Второй'] as $name) {
            $this->actingAs($admin)->post('/admin/delivery-sheets', [
                'date' => '2026-11-12', 'name' => $name, 'driver' => 'Водитель', 'stores' => [$store->id],
            ])->assertRedirect();
        }
        /** @var list<int> $ids */
        $ids = DB::table('bakery_delivery_sheets')->orderBy('id')->pluck('id')->all();
        $this->assertCount(2, $ids);
        $this->actingAs($admin)->post('/admin/delivery-sheet-prints', ['sheet_id' => $ids[0]])->assertOk();
        /** @var object{id:int}|null $firstPrint */
        $firstPrint = DB::table('bakery_delivery_sheet_prints')->first();
        $this->assertNotNull($firstPrint);
        $this->actingAs($admin)->get('/admin/delivery-routes?date=2026-11-12')->assertOk()
            ->assertDontSee('Напечатана:')->assertDontSee('Печать недоступна:');
        $this->actingAs($admin)->get('/admin/delivery-sheets/'.$ids[1])->assertOk()
            ->assertSee('Печать')->assertDontSee('Печать недоступна:');
        $this->actingAs($admin)->post('/admin/delivery-sheet-prints', ['sheet_id' => $ids[1]])
            ->assertOk();
        $this->assertSame(2, DB::table('bakery_delivery_sheet_prints')->count());
        $this->actingAs($admin)->post('/admin/delivery-sheets/'.$ids[0].'/delete')->assertRedirect('/admin/delivery-routes');
        $this->assertFalse(DB::table('bakery_delivery_sheets')->where('id', $ids[0])->exists());
        $this->assertSame(2, DB::table('bakery_delivery_sheet_prints')->count());
        $this->actingAs($admin)->get('/admin/delivery-sheet-prints/'.$firstPrint->id)->assertOk()->assertSee('Первый');
        $this->actingAs($admin)->get('/admin/delivery-routes?date=2026-11-12')->assertOk()
            ->assertDontSee('/admin/delivery-sheets/'.$ids[0].'/edit', false)
            ->assertSee('Удалить');
        $this->actingAs($admin)->post('/admin/delivery-sheets', [
            'date' => '2026-11-12', 'name' => 'Третий', 'driver' => 'Водитель', 'stores' => [$other->id],
        ])->assertRedirect();
    }
}
