<?php

namespace Domains\Bakery\Tests\Feature;

use Domains\Bakery\Domain\Models\Bakery;
use Domains\User\Domain\Enums\UserRole;
use Domains\User\Domain\Enums\UserStatus;
use Domains\User\Domain\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Cookie;

class PersistentWebSessionTest extends TestCase
{
    use DatabaseTransactions;

    public function test_store_login_survives_recreating_session_and_auth_services(): void
    {
        config()->set('session.driver', 'database');
        $this->app['session']->forgetDrivers();

        $store = Bakery::query()->create(['name' => 'Магазин', 'address' => 'Адрес', 'is_active' => true]);
        $user = User::factory()->create([
            'email' => 'persistent-session@example.test',
            'password' => Hash::make('same-password'),
            'role' => UserRole::User,
            'status' => UserStatus::Verify,
            'bakery_id' => $store->id,
        ]);

        $login = $this->post('/login', ['email' => $user->email, 'password' => 'same-password']);
        $login->assertRedirect('/orders');
        $this->assertTrue(DB::table('sessions')->where('user_id', $user->id)->exists());

        $cookie = null;
        foreach ($login->headers->getCookies() as $responseCookie) {
            if ($responseCookie->getName() === config('session.cookie')) {
                $cookie = $responseCookie;
                break;
            }
        }
        $this->assertInstanceOf(Cookie::class, $cookie);
        $cookieValue = $cookie->getValue();
        $this->assertIsString($cookieValue);

        $this->app['auth']->forgetGuards();
        $this->app['session']->forgetDrivers();
        $this->app->forgetInstance('session.store');

        $this->withUnencryptedCookie($cookie->getName(), $cookieValue)
            ->get('/orders')->assertOk();
        $this->assertAuthenticatedAs($user);
    }
}
