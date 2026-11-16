<?php

namespace Domains\DeliverySheet\Providers;

use Domains\DeliverySheet\Application\Repositories\DeliverySheetRepositoryInterface;
use Domains\DeliverySheet\Infrastructure\Repositories\DeliverySheetRepository;
use Illuminate\Support\ServiceProvider;

class DeliverySheetServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(DeliverySheetRepositoryInterface::class, DeliverySheetRepository::class);
    }
}
