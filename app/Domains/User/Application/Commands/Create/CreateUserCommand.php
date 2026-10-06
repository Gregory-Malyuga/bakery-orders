<?php

namespace Domains\User\Application\Commands\Create;

use Domains\User\Domain\Enums\UserRole;
use Domains\User\Domain\Models\User;
use Shared\Bus\BaseCommand;

readonly class CreateUserCommand implements BaseCommand
{
    public function __construct(
        public string $name,
        public string $email,
        public string $password,
        public UserRole $role,
        public ?User $actor = null,
    ) {}
}
