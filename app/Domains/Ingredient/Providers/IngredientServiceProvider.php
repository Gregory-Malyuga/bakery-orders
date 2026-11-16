<?php

namespace Domains\Ingredient\Providers;

use Domains\Ingredient\Application\Repositories\IngredientRepositoryInterface;
use Domains\Ingredient\Infrastructure\Repositories\IngredientRepository;
use Illuminate\Support\ServiceProvider;

class IngredientServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(IngredientRepositoryInterface::class, IngredientRepository::class);
    }
}
