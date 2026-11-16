<?php

namespace Domains\Catalog\Application\Repositories;

interface TransactionRunnerInterface
{
    /** @param callable():void $callback */
    public function run(callable $callback): void;
}
