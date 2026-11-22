<?php

namespace Domains\StockItem\Providers;

use Domains\StockItem\Application\Repositories\IngredientStockReadRepositoryInterface;
use Domains\StockItem\Application\Repositories\StockDashboardReadRepositoryInterface;
use Domains\StockItem\Application\Repositories\StockItemRepositoryInterface;
use Domains\StockItem\Infrastructure\Repositories\IngredientStockReadRepository;
use Domains\StockItem\Infrastructure\Repositories\StockDashboardReadRepository;
use Domains\StockItem\Infrastructure\Repositories\StockItemRepository;
use Illuminate\Support\ServiceProvider;

class StockItemServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(StockItemRepositoryInterface::class, StockItemRepository::class);
        $this->app->bind(StockDashboardReadRepositoryInterface::class, StockDashboardReadRepository::class);
        $this->app->bind(IngredientStockReadRepositoryInterface::class, IngredientStockReadRepository::class);
    }
}
