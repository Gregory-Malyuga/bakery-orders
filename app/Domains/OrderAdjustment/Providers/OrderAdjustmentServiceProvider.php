<?php

namespace Domains\OrderAdjustment\Providers;

use Domains\OrderAdjustment\Application\Repositories\OrderAdjustmentReadRepositoryInterface;
use Domains\OrderAdjustment\Application\Repositories\OrderAdjustmentRepositoryInterface;
use Domains\OrderAdjustment\Infrastructure\Repositories\OrderAdjustmentReadRepository;
use Domains\OrderAdjustment\Infrastructure\Repositories\OrderAdjustmentRepository;
use Illuminate\Support\ServiceProvider;

class OrderAdjustmentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(OrderAdjustmentRepositoryInterface::class, OrderAdjustmentRepository::class);
        $this->app->bind(OrderAdjustmentReadRepositoryInterface::class, OrderAdjustmentReadRepository::class);
    }
}
