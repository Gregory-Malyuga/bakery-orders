<?php

namespace Domains\DeliverySchedule\Providers;

use Domains\DeliverySchedule\Application\Repositories\ClosedDateRepositoryInterface;
use Domains\DeliverySchedule\Infrastructure\Repositories\ClosedDateRepository;
use Illuminate\Support\ServiceProvider;

class DeliveryScheduleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ClosedDateRepositoryInterface::class, ClosedDateRepository::class);
    }
}
