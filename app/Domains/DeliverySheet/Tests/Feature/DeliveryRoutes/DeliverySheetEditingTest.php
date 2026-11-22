<?php

namespace Domains\DeliverySheet\Tests\Feature\DeliveryRoutes;

use Domains\DeliverySheet\Tests\Feature\Support\DeliveryRoutesCase;
use Domains\Store\Domain\Models\Store;
use Domains\User\Domain\Enums\UserRole;
use Domains\User\Domain\Enums\UserStatus;
use Domains\User\Domain\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DeliverySheetEditingTest extends DeliveryRoutesCase
{
    public function test_saved_sheet_can_be_copied_and_changed_without_editing_original(): void
    {
        $this->withoutMiddleware(PreventRequestForgery::class);
        Carbon::setTestNow(Carbon::parse('2026-11-11 19:00:00', 'Europe/Moscow'));
        $admin = User::factory()->create(['role' => UserRole::Admin, 'status' => UserStatus::Verify]);
        $a = Store::query()->create(['name' => 'Точка А', 'address' => 'Адрес А', 'is_active' => true]);
        $b = Store::query()->create(['name' => 'Точка Б', 'address' => 'Адрес Б', 'is_active' => true]);

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

    public function test_saved_sheet_can_be_edited_with_driver_pay_and_reordered_stops(): void
    {
        $this->withoutMiddleware(PreventRequestForgery::class);
        Carbon::setTestNow(Carbon::parse('2026-11-11 19:00:00', 'Europe/Moscow'));
        $admin = User::factory()->create(['role' => UserRole::Admin, 'status' => UserStatus::Verify]);
        $a = Store::query()->create(['name' => 'Точка А', 'address' => 'Адрес А', 'is_active' => true]);
        $b = Store::query()->create(['name' => 'Точка Б', 'address' => 'Адрес Б', 'is_active' => true]);

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
}
