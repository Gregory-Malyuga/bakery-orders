<?php

namespace Domains\Production\Providers;

use Domains\Ingredient\Application\IngredientCatalog;
use Domains\Production\Application\ProductionCalculator;
use Domains\Recipe\Application\ProductionRecipeStore;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\ServiceProvider;

class ProductionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ProductionCalculator::class, function (
            Container $app,
        ): ProductionCalculator {
            return new ProductionCalculator(
                $app->make(ProductionRecipeStore::class),
                $app->make(IngredientCatalog::class),
            );
        });
    }
}
