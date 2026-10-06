<?php

namespace Domains\User\Tests\Unit;

use Domains\User\Application\Commands\Register\RegisterCommand;
use Domains\User\Application\Commands\Register\RegisterHandler;
use Domains\User\Application\Repositories\UserRepositoryInterface;
use Domains\User\Domain\Enums\UserRole;
use Domains\User\Domain\Events\UserCreated;
use Domains\User\Domain\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;

class RegisterHandlerTest extends TestCase
{
    use DatabaseTransactions;

    private RegisterHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->handler = new RegisterHandler(app(UserRepositoryInterface::class));
    }

    public function test_creates_user_with_user_role(): void
    {
        $id = $this->handler->handle(new RegisterCommand('Test User', 'register@test.com', 'password123'));

        $this->assertGreaterThan(0, $id);

        $user = User::findOrFail($id);
        $this->assertEquals('Test User', $user->name);
        $this->assertEquals('register@test.com', $user->email);
        $this->assertEquals(UserRole::User, $user->role);
    }

    public function test_hashes_password(): void
    {
        $id = $this->handler->handle(new RegisterCommand('Test', 'hash@test.com', 'secret123'));

        $user = User::findOrFail($id);
        $this->assertTrue(Hash::check('secret123', $user->password));
    }

    public function test_dispatches_user_created_event(): void
    {
        Event::fake([UserCreated::class]);

        $this->handler->handle(new RegisterCommand('Test', 'event@test.com', 'password123'));

        Event::assertDispatched(UserCreated::class);
    }
}
