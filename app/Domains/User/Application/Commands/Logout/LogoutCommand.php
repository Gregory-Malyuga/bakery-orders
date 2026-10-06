<?php

namespace Domains\User\Application\Commands\Logout;

use Shared\Bus\BaseCommand;

readonly class LogoutCommand implements BaseCommand
{
    public function __construct(public int $tokenId) {}
}
