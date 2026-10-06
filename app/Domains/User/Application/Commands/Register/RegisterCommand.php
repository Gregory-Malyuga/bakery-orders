<?php

namespace Domains\User\Application\Commands\Register;

use Shared\Bus\BaseCommand;

readonly class RegisterCommand implements BaseCommand
{
    public function __construct(
        public string $name,
        public string $email,
        public string $password,
    ) {}
}
