<?php

namespace Domains\User\Application\Commands\Update;

use Domains\User\Application\Repositories\UserRepositoryInterface;
use Domains\User\Domain\Enums\UserRole;
use Domains\User\Domain\Events\UserUpdated;
use Domains\User\Domain\Exceptions\UserInsufficientRoleException;
use Illuminate\Support\Facades\Hash;
use Shared\Bus\CommandHandlerInterface;

readonly class UpdateUserHandler implements CommandHandlerInterface
{
    public function __construct(private UserRepositoryInterface $repository) {}

    public function handle(object $message): null
    {
        assert($message instanceof UpdateUserCommand);

        $record = $this->repository->findOrFail($message->id);

        if ($message->actor !== null && $message->actor->role !== UserRole::Admin) {
            if ($message->role === UserRole::Admin) {
                throw UserInsufficientRoleException::cannotAssignRole($message->role->value);
            }

            if ($record->role === UserRole::Manager) {
                throw UserInsufficientRoleException::cannotManageUser();
            }
        }

        $data = [
            'name' => $message->name,
            'email' => $message->email,
            'role' => $message->role->value,
        ];

        if ($message->password !== null && $message->password !== '') {
            $data['password'] = Hash::make($message->password);
        }

        $updated = $this->repository->update($record, $data);

        UserUpdated::dispatch($updated);

        return null;
    }
}
