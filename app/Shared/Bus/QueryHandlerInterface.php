<?php

namespace Shared\Bus;

interface QueryHandlerInterface
{
    public function handle(object $message): object;
}
