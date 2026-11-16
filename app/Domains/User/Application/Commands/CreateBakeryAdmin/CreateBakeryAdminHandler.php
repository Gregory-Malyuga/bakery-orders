<?php

namespace Domains\User\Application\Commands\CreateBakeryAdmin;

use Domains\User\Application\Repositories\UserRepositoryInterface;
use Domains\User\Domain\Enums\UserRole;
use Domains\User\Domain\Enums\UserStatus;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Shared\Bus\CommandHandlerInterface;

readonly class CreateBakeryAdminHandler implements CommandHandlerInterface
{
    public function __construct(private UserRepositoryInterface $users) {}

    public function handle(object $message): ?int
    {
        assert($message instanceof CreateBakeryAdminCommand);

        if ($this->users->findByEmail($message->email) !== null) {
            throw ValidationException::withMessages(['email' => 'Аккаунт с такой почтой уже существует.']);
        }

        $user = $this->users->create([
            'name' => $message->name,
            'email' => $message->email,
            'password' => Hash::make($message->password),
            'role' => UserRole::Admin,
            'status' => UserStatus::Verify,
            'bakery_id' => null,
        ]);

        return (int) $user->id;
    }
}
