<?php

namespace Domains\User\Application\Commands\Delete;

use Domains\User\Domain\Models\User;
use Shared\Bus\BaseCommand;

readonly class DeleteUserCommand implements BaseCommand
{
    public function __construct(
        public int $id,
        public ?User $actor = null,
    ) {}
}
