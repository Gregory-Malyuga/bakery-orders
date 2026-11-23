<?php

namespace Domains\DeliverySheet\Tests\Feature\DeliveryRoutes;

use Domains\Store\Domain\Models\Store;
use Domains\Order\Domain\Models\Order;
use Domains\Product\Domain\Models\Product;
use Domains\DeliverySheet\Tests\Feature\Support\DeliveryRoutesCase;
use Domains\User\Domain\Enums\UserRole;
use Domains\User\Domain\Enums\UserStatus;
use Domains\User\Domain\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DeliverySheetPrintingTest extends DeliveryRoutesCase
{
    public function testPrintedAmountShowsOrderReturnAndAmountToCollect(): void
    {
        $this->withoutMiddleware(PreventRequestForgery::class);
        Carbon::setTestNow(Carbon::parse('2026-11-11 19:00:00', 'Europe/Moscow'));
        $admin = User::factory()->create(['role' => UserRole::Admin, 'status' => UserStatus::Verify]);
        $store = Store::query()->create(['name' => 'Точка', 'address' => 'Адрес', 'is_active' => true]);
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

    public function testOnlySavedSheetCanBePrintedAndArchived(): void
    {
        $this->withoutMiddleware(PreventRequestForgery::class);
        Carbon::setTestNow(Carbon::parse('2026-11-11 19:00:00', 'Europe/Moscow'));
        $admin = User::factory()->create(['role' => UserRole::Admin, 'status' => UserStatus::Verify]);
        $store = Store::query()->create(['name' => 'Точка', 'address' => 'Адрес', 'is_active' => true]);
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

    public function testPrintingOverlappingSheetsAndDeletingOneKeepsPrintHistory(): void
    {
        $this->withoutMiddleware(PreventRequestForgery::class);
        Carbon::setTestNow(Carbon::parse('2026-11-11 19:00:00', 'Europe/Moscow'));
        $admin = User::factory()->create(['role' => UserRole::Admin, 'status' => UserStatus::Verify]);
        $store = Store::query()->create(['name' => 'Точка А', 'address' => 'Адрес А', 'is_active' => true]);
        $other = Store::query()->create(['name' => 'Точка Б', 'address' => 'Адрес Б', 'is_active' => true]);

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

    public function testSameRoutePrintedForAnotherDateUsesThatDatesOrdersAndCashPayment(): void
    {
        $this->withoutMiddleware(PreventRequestForgery::class);
        Carbon::setTestNow(Carbon::parse('2026-11-13 19:00:00', 'Europe/Moscow'));
        $admin = User::factory()->create(['role' => UserRole::Admin, 'status' => UserStatus::Verify]);
        $cash = Store::query()->create(['name' => 'Наличная точка', 'address' => 'А', 'is_active' => true]);
        $bank = Store::query()->create(['name' => 'Безналичная точка', 'address' => 'Б', 'is_active' => true, 'is_cashless' => true]);
        foreach (['2026-11-13' => [100000, 200000], '2026-11-14' => [150000, 300000]] as $date => $amounts) {
            foreach ([$cash, $bank] as $index => $store) {
                Order::query()->create(['bakery_id' => $store->id, 'delivery_date' => $date, 'status' => 'accepted', 'active_slot' => 1, 'request_key' => (string) Str::uuid(), 'store_name' => $store->name, 'store_address' => $store->address, 'total_kopecks' => $amounts[$index]]);
            }
        }
        $this->actingAs($admin)->post('/admin/delivery-sheets', ['date' => '2026-11-13', 'name' => 'Маршрут', 'driver' => 'Водитель', 'salary' => '100', 'bonus' => '25', 'stores' => [$cash->id, $bank->id]])->assertRedirect();
        /** @var object{id:int,snapshot:string}|null $saved */
        $saved = DB::table('bakery_delivery_sheets')->first();
        $this->assertNotNull($saved);
        $this->actingAs($admin)->post('/admin/delivery-sheet-prints', ['sheet_id' => $saved->id])->assertOk()->assertSee('14.11.2026')->assertSee('1 500,00')->assertSee('Безналичный расчёт')->assertSee('1 375,00');
        /** @var object{id:int,snapshot:string}|null $print */
        $print = DB::table('bakery_delivery_sheet_prints')->first();
        $this->assertNotNull($print);
        /** @var array{date:string,cash_due:int,net_cash:int,rows:list<array{cashless:bool}>} $snapshot */
        $snapshot = json_decode($print->snapshot, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('2026-11-14', $snapshot['date']);
        $this->assertSame(150000, $snapshot['cash_due']);
        $this->assertSame(137500, $snapshot['net_cash']);
        $this->assertSame([false, true], array_column($snapshot['rows'], 'cashless'));
        /** @var array{date:string} $originalSnapshot */
        $originalSnapshot = json_decode($saved->snapshot, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('2026-11-13', $originalSnapshot['date']);
        $this->actingAs($admin)->get('/admin/delivery-sheets/'.$saved->id)->assertOk()->assertSee('14.11.2026')->assertSee('1 375,00');
        $this->actingAs($admin)->get('/admin/delivery-sheet-prints/'.$print->id)->assertOk()->assertSee('14.11.2026')->assertSee('1 375,00');
        $this->actingAs($admin)->post('/admin/delivery-sheets/'.$saved->id.'/edit', ['date' => '2026-11-13', 'name' => 'Маршрут', 'driver' => 'Водитель', 'salary' => '100', 'bonus' => '-1', 'stores' => [$cash->id]])->assertSessionHasErrors('bonus');
        $this->actingAs($admin)->post('/admin/stores', ['id' => $bank->id, 'name' => $bank->name, 'address' => $bank->address, 'is_active' => 1, 'is_cashless' => 0])->assertRedirect();
        $updatedBank = $bank->fresh();
        $this->assertNotNull($updatedBank);
        $this->assertFalse($updatedBank->is_cashless);
        $this->actingAs($admin)->post('/admin/delivery-sheet-prints', ['sheet_id' => $saved->id])->assertOk()->assertSee('4 375,00');
        $this->actingAs($admin)->get('/admin/delivery-sheet-prints/'.$print->id)->assertOk()->assertSee('Безналичный расчёт')->assertSee('1 375,00');
    }
}
