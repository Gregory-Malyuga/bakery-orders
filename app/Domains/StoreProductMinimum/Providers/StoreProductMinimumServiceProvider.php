<?php

namespace Domains\StoreProductMinimum\Providers;

use Domains\StoreProductMinimum\Application\Repositories\StoreProductMinimumReadRepositoryInterface;
use Domains\StoreProductMinimum\Application\Repositories\StoreProductMinimumRepositoryInterface;
use Domains\StoreProductMinimum\Infrastructure\Repositories\StoreProductMinimumReadRepository;
use Domains\StoreProductMinimum\Infrastructure\Repositories\StoreProductMinimumRepository;
use Illuminate\Support\ServiceProvider;

class StoreProductMinimumServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            StoreProductMinimumRepositoryInterface::class,
            StoreProductMinimumRepository::class,
        );
        $this->app->bind(
            StoreProductMinimumReadRepositoryInterface::class,
            StoreProductMinimumReadRepository::class,
        );
    }
}
