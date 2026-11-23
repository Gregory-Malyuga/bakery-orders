<?php

namespace Domains\StoreGroup\Providers;

use Domains\StoreGroup\Application\Repositories\GroupInvoiceReadRepositoryInterface;
use Domains\StoreGroup\Application\Repositories\LabelGroupExclusionRepositoryInterface;
use Domains\StoreGroup\Application\Repositories\StoreGroupRepositoryInterface;
use Domains\StoreGroup\Infrastructure\Repositories\GroupInvoiceReadRepository;
use Domains\StoreGroup\Infrastructure\Repositories\LabelGroupExclusionRepository;
use Domains\StoreGroup\Infrastructure\Repositories\StoreGroupRepository;
use Illuminate\Support\ServiceProvider;

class StoreGroupServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(StoreGroupRepositoryInterface::class, StoreGroupRepository::class);
        $this->app->bind(
            GroupInvoiceReadRepositoryInterface::class,
            GroupInvoiceReadRepository::class,
        );
        $this->app->bind(
            LabelGroupExclusionRepositoryInterface::class,
            LabelGroupExclusionRepository::class,
        );
    }
}
