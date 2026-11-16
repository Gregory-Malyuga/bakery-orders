<?php

namespace Domains\Catalog\Infrastructure\Repositories;

use Domains\Catalog\Application\Repositories\TransactionRunnerInterface;
use Illuminate\Support\Facades\DB;

final class TransactionRunner implements TransactionRunnerInterface
{
    public function run(callable $callback): void
    {
        DB::transaction(static function () use ($callback): void {
            $callback();
        });
    }
}
