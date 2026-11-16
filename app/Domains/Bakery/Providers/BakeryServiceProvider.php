<?php

namespace Domains\Bakery\Providers;

use Domains\Ingredient\Application\IngredientCatalog;
use Domains\Bakery\Application\ProductionCalculator;
use Domains\Bakery\Application\ProductionRecipeStore;
use Domains\Bakery\Application\Repositories\OrderingRepositoryInterface;
use Domains\Bakery\Infrastructure\Repositories\OrderingRepository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\ServiceProvider;

class BakeryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(OrderingRepositoryInterface::class, OrderingRepository::class);
        $this->app->bind(\Domains\Bakery\Application\Exports\InvoiceWorkbookInterface::class, \Domains\Bakery\Infrastructure\Exports\MatrixInvoiceWorkbook::class);
        $this->app->bind(\Domains\Bakery\Application\Exports\PeriodReportWorkbookInterface::class, \Domains\Bakery\Infrastructure\Exports\PeriodReportWorkbook::class);
        $this->app->bind(ProductionCalculator::class, function (Container $app): ProductionCalculator {
            /** @var ProductionRecipeStore $store */
            $store = $app->make(ProductionRecipeStore::class);
            /** @var IngredientCatalog $catalog */
            $catalog = $app->make(IngredientCatalog::class);

            return new ProductionCalculator($store, $catalog);
        });
    }

}
