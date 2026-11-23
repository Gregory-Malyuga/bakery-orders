<?php

namespace Domains\Price\Providers;

use Domains\Price\Application\Repositories\PriceAccountMembershipRepositoryInterface;
use Domains\Price\Application\Repositories\PriceAvailabilityReadRepositoryInterface;
use Domains\Price\Application\Repositories\PriceCatalogImportRepositoryInterface;
use Domains\Price\Application\Repositories\PriceHistoryCatalogReadRepositoryInterface;
use Domains\Price\Application\Repositories\PriceOperationsRepositoryInterface;
use Domains\Price\Application\Repositories\PriceOrderReadRepositoryInterface;
use Domains\Price\Application\Repositories\PriceReferenceImportRepositoryInterface;
use Domains\Price\Infrastructure\Repositories\PriceAccountMembershipRepository;
use Domains\Price\Infrastructure\Repositories\PriceAvailabilityReadRepository;
use Domains\Price\Infrastructure\Repositories\PriceCatalogImportRepository;
use Domains\Price\Infrastructure\Repositories\PriceHistoryCatalogReadRepository;
use Domains\Price\Infrastructure\Repositories\PriceOperationsRepository;
use Domains\Price\Infrastructure\Repositories\PriceOrderReadRepository;
use Domains\Price\Infrastructure\Repositories\PriceReferenceImportRepository;
use Illuminate\Support\ServiceProvider;

class PriceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            PriceAccountMembershipRepositoryInterface::class,
            PriceAccountMembershipRepository::class,
        );
        $this->app->bind(
            PriceOperationsRepositoryInterface::class,
            PriceOperationsRepository::class,
        );
        $this->app->bind(
            PriceAvailabilityReadRepositoryInterface::class,
            PriceAvailabilityReadRepository::class,
        );
        $this->app->bind(
            PriceCatalogImportRepositoryInterface::class,
            PriceCatalogImportRepository::class,
        );
        $this->app->bind(
            PriceReferenceImportRepositoryInterface::class,
            PriceReferenceImportRepository::class,
        );
        $this->app->bind(PriceOrderReadRepositoryInterface::class, PriceOrderReadRepository::class);
        $this->app->bind(
            PriceHistoryCatalogReadRepositoryInterface::class,
            PriceHistoryCatalogReadRepository::class,
        );
    }
}
