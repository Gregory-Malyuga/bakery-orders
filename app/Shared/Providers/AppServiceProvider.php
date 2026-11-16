<?php

namespace Shared\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Shared\Bus\CommandBusInterface;
use Shared\Bus\LaravelCommandBus;
use Shared\Bus\LaravelQueryBus;
use Shared\Bus\QueryBusInterface;
use Shared\Cache\CacheWarmCommand;
use Shared\Console\Commands\GenerateOpenApiDocsCommand;
use Shared\Console\Commands\MakeDomainCommand;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CommandBusInterface::class, LaravelCommandBus::class);
        $this->app->singleton(QueryBusInterface::class, LaravelQueryBus::class);
    }

    public function boot(): void
    {
        RateLimiter::for('public-api', function (Request $request): Limit {
            return Limit::perMinute(60)->by($request->ip());
        });

        RateLimiter::for('auth-login', function (Request $request): Limit {
            return Limit::perMinute(10)->by($request->ip());
        });

        if ($this->app->runningInConsole()) {
            $this->commands([
                CacheWarmCommand::class,
                GenerateOpenApiDocsCommand::class,
                MakeDomainCommand::class,
            ]);
        }
    }
}
