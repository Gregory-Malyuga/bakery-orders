<?php

namespace Domains\StoreAccount\Providers;

use Domains\StoreAccount\Application\Repositories\StoreAccountRepositoryInterface;
use Domains\StoreAccount\Infrastructure\Repositories\StoreAccountRepository;
use Domains\StoreAccount\Application\Repositories\StoreAccountMembershipRepositoryInterface;
use Domains\StoreAccount\Infrastructure\Repositories\StoreAccountMembershipRepository;
use Domains\StoreAccount\Application\Repositories\StoreOwnerReadRepositoryInterface;
use Domains\StoreAccount\Infrastructure\Repositories\StoreOwnerReadRepository;
use Illuminate\Support\ServiceProvider;

class StoreAccountServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(StoreAccountRepositoryInterface::class, StoreAccountRepository::class);
        $this->app->bind(StoreAccountMembershipRepositoryInterface::class, StoreAccountMembershipRepository::class);
        $this->app->bind(StoreOwnerReadRepositoryInterface::class, StoreOwnerReadRepository::class);
    }
}
