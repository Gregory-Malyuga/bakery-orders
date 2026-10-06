<?php

namespace Domains\User\Application\Commands\Delete;

use Domains\User\Application\Repositories\UserRepositoryInterface;
use Domains\User\Domain\Enums\UserRole;
use Domains\User\Domain\Events\UserDeleted;
use Domains\User\Domain\Exceptions\UserInsufficientRoleException;
use Shared\Bus\CommandHandlerInterface;

readonly class DeleteUserHandler implements CommandHandlerInterface
{
    public function __construct(private UserRepositoryInterface $repository) {}

    public function handle(object $message): null
    {
        assert($message instanceof DeleteUserCommand);

        $record = $this->repository->findOrFail($message->id);

        if ($message->actor !== null && $message->actor->role !== UserRole::Admin) {
            if ($record->role === UserRole::Admin) {
                throw UserInsufficientRoleException::cannotDeleteAdmin();
            }

            if ($record->role === UserRole::Manager) {
                throw UserInsufficientRoleException::cannotManageUser();
            }
        }

        $id = $record->id;
        $this->repository->delete($record);
        UserDeleted::dispatch($id);

        return null;
    }
}
