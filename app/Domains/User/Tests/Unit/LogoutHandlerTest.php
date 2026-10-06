<?php

namespace Domains\User\Tests\Unit;

use Domains\User\Application\Commands\Logout\LogoutCommand;
use Domains\User\Application\Commands\Logout\LogoutHandler;
use Domains\User\Application\Repositories\UserRepositoryInterface;
use Domains\User\Domain\Enums\UserRole;
use Domains\User\Domain\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Laravel\Sanctum\PersonalAccessToken;

class LogoutHandlerTest extends TestCase
{
    use DatabaseTransactions;

    private LogoutHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->handler = new LogoutHandler(app(UserRepositoryInterface::class));
    }

    public function test_deletes_token(): void
    {
        $user = User::factory()->create(['role' => UserRole::User]);
        $tokenId = $user->createToken('api')->accessToken->id;

        $this->handler->handle(new LogoutCommand($tokenId));

        $this->assertNull(PersonalAccessToken::find($tokenId));
    }

    public function test_noop_for_nonexistent_token(): void
    {
        $this->expectNotToPerformAssertions();

        $this->handler->handle(new LogoutCommand(PHP_INT_MAX));
    }
}
