<?php

namespace Domains\User\Application\Commands\Logout;

use Domains\User\Application\Repositories\UserRepositoryInterface;
use Shared\Bus\CommandHandlerInterface;

readonly class LogoutHandler implements CommandHandlerInterface
{
    public function __construct(private UserRepositoryInterface $repository) {}

    public function handle(object $message): null
    {
        assert($message instanceof LogoutCommand);

        $this->repository->deleteToken($message->tokenId);

        return null;
    }
}
