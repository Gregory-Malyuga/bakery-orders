<?php

namespace Domains\User\Application\Commands\Update;

use Domains\User\Domain\Enums\UserRole;
use Domains\User\Domain\Models\User;
use Shared\Bus\BaseCommand;

readonly class UpdateUserCommand implements BaseCommand
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public ?string $password,
        public UserRole $role,
        public ?User $actor = null,
    ) {}
}
