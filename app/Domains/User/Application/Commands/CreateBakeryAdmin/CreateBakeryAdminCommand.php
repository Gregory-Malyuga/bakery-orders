<?php

namespace Domains\User\Application\Commands\CreateBakeryAdmin;

use Shared\Bus\BaseCommand;

readonly class CreateBakeryAdminCommand implements BaseCommand
{
    public function __construct(
        public string $email,
        public string $name,
        public string $password,
    ) {}
}
