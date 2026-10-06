<?php

namespace Domains\User\Application\Commands\Register;

use Domains\User\Application\Repositories\UserRepositoryInterface;
use Domains\User\Domain\Enums\UserRole;
use Domains\User\Domain\Events\UserCreated;
use Domains\User\Domain\Models\User;
use Illuminate\Support\Facades\Hash;
use Shared\Bus\CommandHandlerInterface;

readonly class RegisterHandler implements CommandHandlerInterface
{
    public function __construct(private UserRepositoryInterface $repository) {}

    public function handle(object $message): int
    {
        assert($message instanceof RegisterCommand);

        /** @var User $user */
        $user = $this->repository->create([
            'name' => $message->name,
            'email' => $message->email,
            'password' => Hash::make($message->password),
            'role' => UserRole::User,
        ]);

        UserCreated::dispatch($user);

        return $user->id;
    }
}
