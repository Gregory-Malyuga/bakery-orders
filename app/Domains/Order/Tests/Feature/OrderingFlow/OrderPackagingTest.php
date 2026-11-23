<?php

namespace Domains\Order\Tests\Feature\OrderingFlow;

use Carbon\Carbon;
use Domains\Order\Application\OrderingService;
use Domains\Order\Application\Repositories\OrderRepositoryInterface;
use Domains\Order\Tests\Feature\Support\OrderingFlowCase;
use Domains\OrderAdjustment\Application\Commands\Adjust\AdjustOrderCommand;
use Domains\OrderAdjustment\Application\Commands\Adjust\AdjustOrderHandler;
use Domains\User\Domain\Enums\UserRole;
use Domains\User\Domain\Enums\UserStatus;
use Domains\User\Domain\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrderPackagingTest extends OrderingFlowCase
{
    public function test_product_minimum_is_enforced_on_preview_and_final_submission(): void
    {
        $this->withoutMiddleware(PreventRequestForgery::class);
        Carbon::setTestNow(Carbon::parse('2026-10-06 12:00:00', 'Europe/Moscow'));
        [$store, $user, $product] = $this->shop('Пятый', 3000);
        $service = app(OrderingService::class);
        $oldQuote = $service->quote($store->id, [$product->id => 2]);
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'status' => UserStatus::Verify,
        ]);
        $this->actingAs($admin)
            ->post('/admin/products', [
                'id' => $product->id,
                'name' => $product->name,
                'sort_order' => 0,
                'minimum_quantity' => 3,
                'is_active' => 1,
            ])
            ->assertRedirect();
        $this->assertSame(3, $product->fresh()?->minimum_quantity);

        $this->actingAs($user)
            ->post('/orders/preview', [
                'request_key' => (string) Str::uuid(),
                'quantities' => [$product->id => 2],
            ])
            ->assertSessionHasErrors('quantities');
        $this->actingAs($user)
            ->post('/orders', [
                'request_key' => (string) Str::uuid(),
                'quantities' => [$product->id => 2],
                'quote_hash' => $oldQuote['hash'],
            ])
            ->assertSessionHasErrors('quantities');
        $this->assertCount(0, app(OrderRepositoryInterface::class)->orders($oldQuote['date']));

        $newQuote = $service->quote($store->id, [$product->id => 3]);
        $this->assertSame(9000, $newQuote['total']);
        $this->assertNotNull(
            $service->place(
                $store->id,
                (string) Str::uuid(),
                [$product->id => 3],
                $newQuote['hash'],
            )['order'],
        );
    }

    public function test_order_quantities_must_fit_the_selected_packaging(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-06 12:00:00', 'Europe/Moscow'));
        [$store, , $product] = $this->shop('Фасовка', 3000);
        $product->update(['packaging_mode' => 'mixed']);
        $service = app(OrderingService::class);

        foreach ([3, 5, 6, 8, 9, 10, 11, 100000] as $quantity) {
            $this->assertSame(
                $quantity,
                $service->quote($store->id, [$product->id => $quantity])['items'][0]['quantity'],
            );
        }
        foreach ([1, 2, 4, 7] as $quantity) {
            try {
                $service->quote($store->id, [$product->id => $quantity]);
                $this->fail("Количество {$quantity} не должно проходить проверку фасовки.");
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('quantities', $exception->errors());
            }
        }

        $product->update(['packaging_mode' => 'pair']);
        $this->assertSame(
            2,
            $service->quote($store->id, [$product->id => 2])['items'][0]['quantity'],
        );
        try {
            $service->quote($store->id, [$product->id => 3]);
            $this->fail('Нечётное количество не должно проходить проверку фасовки.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('quantities', $exception->errors());
        }
    }

    public function test_admin_cannot_adjust_order_to_quantity_that_does_not_fit_packaging(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-06 12:00:00', 'Europe/Moscow'));
        [$store, , $product] = $this->shop('Исправление фасовки', 3000);
        $product->update(['packaging_mode' => 'mixed']);
        $service = app(OrderingService::class);
        $quote = $service->quote($store->id, [$product->id => 3]);
        $order = $service->place(
            $store->id,
            (string) Str::uuid(),
            [$product->id => 3],
            $quote['hash'],
        )['order'];
        $this->assertNotNull($order);
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'status' => UserStatus::Verify,
        ]);
        try {
            app(AdjustOrderHandler::class)->handle(
                new AdjustOrderCommand(
                    $order->id,
                    $admin->id,
                    [$product->id => 4],
                    'Исправление',
                    null,
                ),
            );
            $this->fail('Корректировка на 4 шт. не должна сохраняться.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('quantities', $exception->errors());
        }
        $this->assertSame(3, $order->fresh()?->items->first()?->quantity);
    }

    public function test_product_and_store_minimums_are_raised_to_packaging_minimum(): void
    {
        $this->withoutMiddleware(PreventRequestForgery::class);
        [$store, , $product] = $this->shop('Минимум фасовки', 3000);
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'status' => UserStatus::Verify,
        ]);
        $this->actingAs($admin)
            ->post('/admin/products', [
                'id' => $product->id,
                'name' => $product->name,
                'sort_order' => 0,
                'minimum_quantity' => 1,
                'packaging_mode' => 'mixed',
                'is_active' => 1,
            ])
            ->assertRedirect();
        $this->assertSame(3, $product->fresh()?->minimum_quantity);
        $this->actingAs($admin)
            ->post('/admin/minimums', [
                'store' => $store->id,
                'product' => $product->id,
                'minimum_quantity' => 1,
            ])
            ->assertRedirect();
        $this->assertSame(
            3,
            DB::table('bakery_store_product_minimums')
                ->where('bakery_id', $store->id)
                ->where('product_id', $product->id)
                ->value('minimum_quantity'),
        );
        $this->actingAs($admin)
            ->post('/admin/products', [
                'id' => $product->id,
                'name' => $product->name,
                'sort_order' => 0,
                'minimum_quantity' => 1,
                'packaging_mode' => 'pair',
                'is_active' => 1,
            ])
            ->assertRedirect();
        $this->assertSame(2, $product->fresh()->minimum_quantity);
        $this->actingAs($admin)
            ->post('/admin/minimums', [
                'store' => $store->id,
                'product' => $product->id,
                'minimum_quantity' => 1,
            ])
            ->assertRedirect();
        $this->assertSame(
            2,
            DB::table('bakery_store_product_minimums')
                ->where('bakery_id', $store->id)
                ->where('product_id', $product->id)
                ->value('minimum_quantity'),
        );
        $this->actingAs($admin)
            ->post('/admin/products', [
                'id' => $product->id,
                'name' => $product->name,
                'sort_order' => 0,
                'minimum_quantity' => 1,
                'packaging_mode' => 'single',
                'is_active' => 1,
            ])
            ->assertRedirect();
        $this->assertSame(1, $product->fresh()->minimum_quantity);
    }
}
