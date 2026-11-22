<?php

namespace Domains\StoreProductAvailability\Providers;

use Domains\StoreProductAvailability\Application\Repositories\AvailabilityCatalogReadRepositoryInterface;
use Domains\StoreProductAvailability\Application\Repositories\StoreProductAvailabilityReadRepositoryInterface;
use Domains\StoreProductAvailability\Application\Repositories\StoreProductAvailabilityRepositoryInterface;
use Domains\StoreProductAvailability\Infrastructure\Repositories\AvailabilityCatalogReadRepository;
use Domains\StoreProductAvailability\Infrastructure\Repositories\StoreProductAvailabilityReadRepository;
use Domains\StoreProductAvailability\Infrastructure\Repositories\StoreProductAvailabilityRepository;
use Illuminate\Support\ServiceProvider;

class StoreProductAvailabilityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(StoreProductAvailabilityRepositoryInterface::class, StoreProductAvailabilityRepository::class);
        $this->app->bind(StoreProductAvailabilityReadRepositoryInterface::class, StoreProductAvailabilityReadRepository::class);
        $this->app->bind(AvailabilityCatalogReadRepositoryInterface::class, AvailabilityCatalogReadRepository::class);
    }
}
