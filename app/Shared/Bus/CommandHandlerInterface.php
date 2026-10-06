<?php

namespace Shared\Bus;

interface CommandHandlerInterface
{
    public function handle(object $message): ?int;
}
