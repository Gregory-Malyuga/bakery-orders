<?php

namespace Domains\Label\Providers;

use Domains\Label\Application\Repositories\LabelGroupRepositoryInterface;
use Domains\Label\Infrastructure\Repositories\LabelGroupRepository;
use Illuminate\Support\ServiceProvider;

class LabelServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(LabelGroupRepositoryInterface::class, LabelGroupRepository::class);
    }
}
