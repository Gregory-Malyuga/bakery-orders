<?php

namespace Domains\User\Application\Queries\FindById;

use Domains\User\Application\Repositories\UserRepositoryInterface;
use Domains\User\Domain\Models\User;
use Shared\Bus\QueryHandlerInterface;

readonly class FindUserByIdHandler implements QueryHandlerInterface
{
    public function __construct(private UserRepositoryInterface $repository) {}

    public function handle(object $message): User
    {
        assert($message instanceof FindUserByIdQuery);

        return $this->repository->findOrFail($message->id);
    }
}
