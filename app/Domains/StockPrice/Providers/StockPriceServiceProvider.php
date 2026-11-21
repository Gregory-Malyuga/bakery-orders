<?php

namespace Domains\StockPrice\Providers;

use Domains\StockPrice\Application\Repositories\StockDiscrepancyPriceReadRepositoryInterface;
use Domains\StockPrice\Application\Repositories\StockPriceReadRepositoryInterface;
use Domains\StockPrice\Infrastructure\Repositories\StockDiscrepancyPriceReadRepository;
use Domains\StockPrice\Infrastructure\Repositories\StockPriceReadRepository;
use Illuminate\Support\ServiceProvider;

class StockPriceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(StockDiscrepancyPriceReadRepositoryInterface::class, StockDiscrepancyPriceReadRepository::class);
        $this->app->bind(StockPriceReadRepositoryInterface::class, StockPriceReadRepository::class);
    }
}
