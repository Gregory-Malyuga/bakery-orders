<?php

namespace Domains\User\Application;

use Domains\User\Application\Repositories\UserRepositoryInterface;

readonly class EmailAvailabilityService
{
    public function __construct(private UserRepositoryInterface $users) {}

    public function isTaken(string $email): bool
    {
        return $this->users->findByEmail($email) !== null;
    }
}
