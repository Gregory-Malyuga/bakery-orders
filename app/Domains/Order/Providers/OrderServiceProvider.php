<?php

namespace Domains\Order\Providers;

use Domains\Order\Application\Repositories\OrderCalendarRepositoryInterface;
use Domains\Order\Application\Repositories\OrderRepositoryInterface;
use Domains\Order\Infrastructure\Repositories\OrderRepository;
use Domains\Order\Application\Repositories\OrderCutoffRepositoryInterface;
use Domains\Order\Infrastructure\Repositories\OrderCutoffRepository;
use Domains\Order\Infrastructure\Repositories\OrderCalendarRepository;
use Domains\Order\Application\Repositories\OrderAnalyticsReadRepositoryInterface;
use Domains\Order\Infrastructure\Repositories\OrderAnalyticsReadRepository;
use Illuminate\Support\ServiceProvider;

class OrderServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(OrderCalendarRepositoryInterface::class, OrderCalendarRepository::class);
        $this->app->bind(OrderRepositoryInterface::class, OrderRepository::class);
        $this->app->bind(OrderCutoffRepositoryInterface::class, OrderCutoffRepository::class);
        $this->app->bind(OrderAnalyticsReadRepositoryInterface::class, OrderAnalyticsReadRepository::class);
    }
}
