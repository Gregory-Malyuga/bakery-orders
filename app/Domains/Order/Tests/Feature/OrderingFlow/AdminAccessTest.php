<?php

namespace Domains\Order\Tests\Feature\OrderingFlow;

use Carbon\Carbon;
use Domains\Order\Application\OrderingService;
use Domains\Order\Tests\Feature\Support\OrderingFlowCase;
use Domains\User\Domain\Enums\UserRole;
use Domains\User\Domain\Enums\UserStatus;
use Domains\User\Domain\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AdminAccessTest extends OrderingFlowCase
{
    public function test_guest_is_redirected_to_login_from_admin(): void
    {
        $this->get('/admin')->assertRedirect('/login');
    }

    public function test_admin_can_change_order_cutoff_and_store_cannot(): void
    {
        $this->withoutMiddleware(PreventRequestForgery::class);
        Carbon::setTestNow(Carbon::parse('2026-10-06 17:14:59', 'Europe/Moscow'));
        [$store, $user, $product] = $this->shop('Новое время', 3000);
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'status' => UserStatus::Verify,
        ]);
        $this->actingAs($user)
            ->post('/admin/settings', ['order_cutoff' => '17:15'])
            ->assertForbidden();
        $this->actingAs($admin)
            ->post('/admin/settings', ['order_cutoff' => '17:15'])
            ->assertRedirect();
        $this->assertSame(
            '17:15',
            DB::table('bakery_settings')->where('key', 'order_cutoff')->value('value'),
        );
        $this->actingAs($admin)
            ->get('/admin/catalog?tab=settings')
            ->assertOk()
            ->assertSee('value="17:15"', false);
        $this->actingAs($user)->get('/orders')->assertOk()->assertSee('До 17:15 МСК');
        $service = app(OrderingService::class);
        $quote = $service->quote($store->id, [$product->id => 1]);
        $this->assertTrue($service->isOpen());
        Carbon::setTestNow(Carbon::parse('2026-10-06 17:15:00', 'Europe/Moscow'));
        $this->assertFalse($service->isOpen());
        $this->actingAs($user)
            ->post('/orders', [
                'request_key' => (string) Str::uuid(),
                'quantities' => [$product->id => 1],
                'quote_hash' => $quote['hash'],
            ])
            ->assertSessionHasErrors('order');
    }
}
