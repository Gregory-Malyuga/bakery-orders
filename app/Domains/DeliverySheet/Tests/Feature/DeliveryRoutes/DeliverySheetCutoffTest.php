<?php

namespace Domains\DeliverySheet\Tests\Feature\DeliveryRoutes;

use Domains\DeliverySheet\Tests\Feature\Support\DeliveryRoutesCase;
use Domains\Order\Domain\Models\Order;
use Domains\Store\Domain\Models\Store;
use Domains\User\Domain\Enums\UserRole;
use Domains\User\Domain\Enums\UserStatus;
use Domains\User\Domain\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DeliverySheetCutoffTest extends DeliveryRoutesCase
{
    public function testSheetCannotBeSavedBeforeCutoff(): void
    {
        $this->withoutMiddleware(PreventRequestForgery::class);
        Carbon::setTestNow(Carbon::parse('2026-11-11 18:00:00', 'Europe/Moscow'));
        $admin = User::factory()->create(['role' => UserRole::Admin, 'status' => UserStatus::Verify]);
        $store = Store::query()->create(['name' => 'Точка', 'address' => 'Адрес', 'is_active' => true]);
        $this->actingAs($admin)->post('/admin/delivery-sheets', ['date' => '2026-11-12', 'name' => 'Маршрут', 'driver' => 'Водитель', 'stores' => [$store->id]])->assertSessionHasErrors('date');
        $this->actingAs($admin)->get('/admin/delivery-routes')->assertOk()->assertViewHas('date', '2026-11-11');
        $this->actingAs($admin)->post('/admin/delivery-sheets', ['date' => '2026-11-11', 'name' => 'Маршрут', 'driver' => 'Водитель', 'stores' => [$store->id]])->assertRedirect();
    }

    public function testSavedRoutePrintsTodayBeforeCutoffEvenIfSavedForAnotherDate(): void
    {
        $this->withoutMiddleware(PreventRequestForgery::class);
        Carbon::setTestNow(Carbon::parse('2026-11-13 17:00:00', 'Europe/Moscow'));
        $admin = User::factory()->create(['role' => UserRole::Admin, 'status' => UserStatus::Verify]);
        $store = Store::query()->create(['name' => 'Точка', 'address' => 'Адрес', 'is_active' => true]);
        Order::query()->create(['bakery_id' => $store->id, 'delivery_date' => '2026-11-13', 'status' => 'accepted', 'active_slot' => 1, 'request_key' => (string) Str::uuid(), 'store_name' => 'Точка', 'store_address' => 'Адрес', 'total_kopecks' => 50000]);
        /** @var int $sheetId */
        $sheetId = DB::table('bakery_delivery_sheets')->insertGetId(['delivery_date' => '2026-11-12', 'snapshot' => json_encode(['date' => '2026-11-12', 'route' => 'Маршрут', 'driver' => 'Водитель', 'salary_kopecks' => null, 'closed' => false, 'rows' => [['store_id' => $store->id, 'name' => 'Точка', 'address' => 'Адрес', 'order' => 10000, 'returns' => 0, 'due' => 10000, 'has_order' => true]], 'order' => 10000, 'returns' => 0, 'due' => 10000], JSON_THROW_ON_ERROR), 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($admin)->get('/admin/delivery-sheets/'.$sheetId)->assertOk()->assertSee('13.11.2026')->assertSee('500,00');
        $this->actingAs($admin)->post('/admin/delivery-sheet-prints', ['sheet_id' => $sheetId])->assertOk()->assertSee('13.11.2026')->assertSee('500,00');
        /** @var object{delivery_date:string}|null $printed */
        $printed = DB::table('bakery_delivery_sheet_prints')->first();
        $this->assertNotNull($printed);
        $this->assertSame('2026-11-13', $printed->delivery_date);
    }
}
