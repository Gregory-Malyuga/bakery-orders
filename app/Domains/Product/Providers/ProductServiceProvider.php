<?php

namespace Domains\Product\Providers;

use Domains\Product\Application\Repositories\ProductCatalogImportRepositoryInterface;
use Domains\Product\Application\Repositories\ProductOptionsReadRepositoryInterface;
use Domains\Product\Application\Repositories\ProductOrderReadRepositoryInterface;
use Domains\Product\Application\Repositories\ProductReferenceImportRepositoryInterface;
use Domains\Product\Application\Repositories\ProductRepositoryInterface;
use Domains\Product\Infrastructure\Repositories\ProductCatalogImportRepository;
use Domains\Product\Infrastructure\Repositories\ProductOptionsReadRepository;
use Domains\Product\Infrastructure\Repositories\ProductOrderReadRepository;
use Domains\Product\Infrastructure\Repositories\ProductReferenceImportRepository;
use Domains\Product\Infrastructure\Repositories\ProductRepository;
use Illuminate\Support\ServiceProvider;

class ProductServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ProductRepositoryInterface::class, ProductRepository::class);
        $this->app->bind(ProductOptionsReadRepositoryInterface::class, ProductOptionsReadRepository::class);
        $this->app->bind(ProductCatalogImportRepositoryInterface::class, ProductCatalogImportRepository::class);
        $this->app->bind(ProductReferenceImportRepositoryInterface::class, ProductReferenceImportRepository::class);
        $this->app->bind(ProductOrderReadRepositoryInterface::class, ProductOrderReadRepository::class);
    }
}
