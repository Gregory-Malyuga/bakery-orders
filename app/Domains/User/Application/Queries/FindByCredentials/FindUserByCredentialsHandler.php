<?php

namespace Domains\User\Application\Queries\FindByCredentials;

use Domains\User\Application\Repositories\UserRepositoryInterface;
use Domains\User\Domain\Exceptions\InvalidCredentialsException;
use Domains\User\Domain\Models\User;
use Illuminate\Support\Facades\Hash;
use Shared\Bus\QueryHandlerInterface;

readonly class FindUserByCredentialsHandler implements QueryHandlerInterface
{
    public function __construct(private UserRepositoryInterface $repository) {}

    public function handle(object $message): User
    {
        assert($message instanceof FindUserByCredentialsQuery);

        $user = $this->repository->findByEmail($message->email);

        if ($user === null || ! Hash::check($message->password, $user->password)) {
            throw new InvalidCredentialsException;
        }

        return $user;
    }
}
