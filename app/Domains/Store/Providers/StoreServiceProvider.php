<?php

namespace Domains\Store\Providers;

use Domains\Store\Application\Repositories\StoreRepositoryInterface;
use Domains\Store\Infrastructure\Repositories\StoreRepository;
use Domains\Store\Application\Repositories\StoreAnalyticsReadRepositoryInterface;
use Domains\Store\Infrastructure\Repositories\StoreAnalyticsReadRepository;
use Domains\Store\Application\Repositories\StoreCatalogImportRepositoryInterface;
use Domains\Store\Infrastructure\Repositories\StoreCatalogImportRepository;
use Domains\Store\Application\Repositories\StoreReferenceImportRepositoryInterface;
use Domains\Store\Infrastructure\Repositories\StoreReferenceImportRepository;
use Domains\Store\Application\Repositories\StoreOrderReadRepositoryInterface;
use Domains\Store\Infrastructure\Repositories\StoreOrderReadRepository;
use Domains\Store\Application\Repositories\StorePriceSourceRepositoryInterface;
use Domains\Store\Infrastructure\Repositories\StorePriceSourceRepository;
use Domains\Store\Application\Repositories\StoreAccountPriceSourceRepositoryInterface;
use Domains\Store\Infrastructure\Repositories\StoreAccountPriceSourceRepository;
use Illuminate\Support\ServiceProvider;

class StoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(StoreAccountPriceSourceRepositoryInterface::class, StoreAccountPriceSourceRepository::class);
        $this->app->bind(StorePriceSourceRepositoryInterface::class, StorePriceSourceRepository::class);
        $this->app->bind(StoreRepositoryInterface::class, StoreRepository::class);
        $this->app->bind(StoreAnalyticsReadRepositoryInterface::class, StoreAnalyticsReadRepository::class);
        $this->app->bind(StoreCatalogImportRepositoryInterface::class, StoreCatalogImportRepository::class);
        $this->app->bind(StoreReferenceImportRepositoryInterface::class, StoreReferenceImportRepository::class);
        $this->app->bind(StoreOrderReadRepositoryInterface::class, StoreOrderReadRepository::class);
    }
}
