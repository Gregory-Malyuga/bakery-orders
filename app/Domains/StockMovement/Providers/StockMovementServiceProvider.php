<?php

namespace Domains\StockMovement\Providers;

use Domains\StockMovement\Application\Repositories\StockMovementDashboardRepositoryInterface;
use Domains\StockMovement\Application\Repositories\StockMovementRepositoryInterface;
use Domains\StockMovement\Infrastructure\Repositories\StockMovementDashboardRepository;
use Domains\StockMovement\Infrastructure\Repositories\StockMovementRepository;
use Illuminate\Support\ServiceProvider;

class StockMovementServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(StockMovementDashboardRepositoryInterface::class, StockMovementDashboardRepository::class);
        $this->app->bind(StockMovementRepositoryInterface::class, StockMovementRepository::class);
    }
}
