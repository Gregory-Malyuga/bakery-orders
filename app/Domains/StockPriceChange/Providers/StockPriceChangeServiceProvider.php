<?php

namespace Domains\StockPriceChange\Providers;

use Domains\StockPriceChange\Application\Repositories\StockPriceChangeRepositoryInterface;
use Domains\StockPriceChange\Infrastructure\Repositories\StockPriceChangeRepository;
use Domains\StockPriceChange\Application\Repositories\StockPriceChangeDashboardRepositoryInterface;
use Domains\StockPriceChange\Infrastructure\Repositories\StockPriceChangeDashboardRepository;
use Illuminate\Support\ServiceProvider;

class StockPriceChangeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(StockPriceChangeDashboardRepositoryInterface::class, StockPriceChangeDashboardRepository::class);
        $this->app->bind(StockPriceChangeRepositoryInterface::class, StockPriceChangeRepository::class);
    }
}
