<?php

namespace Domains\Recipe\Providers;

use Domains\Recipe\Application\Repositories\RecipeOverrideRepositoryInterface;
use Domains\Recipe\Infrastructure\Repositories\RecipeOverrideRepository;
use Illuminate\Support\ServiceProvider;

class RecipeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(RecipeOverrideRepositoryInterface::class, RecipeOverrideRepository::class);
    }
}
