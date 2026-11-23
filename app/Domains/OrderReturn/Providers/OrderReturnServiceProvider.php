<?php

namespace Domains\OrderReturn\Providers;

use Domains\OrderReturn\Application\Repositories\OrderReturnReadRepositoryInterface;
use Domains\OrderReturn\Application\Repositories\OrderReturnRepositoryInterface;
use Domains\OrderReturn\Infrastructure\Repositories\OrderReturnReadRepository;
use Domains\OrderReturn\Infrastructure\Repositories\OrderReturnRepository;
use Illuminate\Support\ServiceProvider;

class OrderReturnServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(OrderReturnRepositoryInterface::class, OrderReturnRepository::class);
        $this->app->bind(
            OrderReturnReadRepositoryInterface::class,
            OrderReturnReadRepository::class,
        );
    }
}
