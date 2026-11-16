<?php

namespace Domains\Catalog\Providers;

use Domains\Catalog\Application\Repositories\CatalogReadRepositoryInterface;
use Domains\Catalog\Infrastructure\Repositories\CatalogReadRepository;
use Domains\Catalog\Application\Repositories\TransactionRunnerInterface;
use Domains\Catalog\Infrastructure\Repositories\TransactionRunner;
use Domains\Catalog\Infrastructure\Console\ImportCatalogCommand;
use Domains\Catalog\Infrastructure\Console\SeedReferenceCatalogCommand;
use Illuminate\Support\ServiceProvider;

class CatalogServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([ImportCatalogCommand::class, SeedReferenceCatalogCommand::class]);
        }
    }

    public function register(): void
    {
        $this->app->bind(CatalogReadRepositoryInterface::class, CatalogReadRepository::class);
        $this->app->bind(TransactionRunnerInterface::class, TransactionRunner::class);
    }
}
