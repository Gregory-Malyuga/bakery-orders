<?php

namespace Tests\Unit\Shared\Cache;

use Shared\Cache\CacheWarmerInterface;
use Tests\TestCase;

class CacheWarmCommandTest extends TestCase
{
    public function test_runs_all_registered_warmers(): void
    {
        $warmerA = new SpyWarmer(priority: 10);
        $warmerB = new SpyWarmer(priority: 20);

        $this->app->bind('warmer-a', fn () => $warmerA);
        $this->app->bind('warmer-b', fn () => $warmerB);
        $this->app->tag(['warmer-a', 'warmer-b'], 'cache-warmers');

        $this->artisan('cache:warm')->assertSuccessful();

        $this->assertTrue($warmerA->warmed);
        $this->assertTrue($warmerB->warmed);
    }

    public function test_runs_warmers_in_descending_priority_order(): void
    {
        $log = new WarmLog;

        $this->app->bind('warmer-low', fn () => new OrderTrackingWarmer(priority: 1, name: 'low', log: $log));
        $this->app->bind('warmer-high', fn () => new OrderTrackingWarmer(priority: 100, name: 'high', log: $log));
        $this->app->bind('warmer-mid', fn () => new OrderTrackingWarmer(priority: 50, name: 'mid', log: $log));
        $this->app->tag(['warmer-low', 'warmer-high', 'warmer-mid'], 'cache-warmers');

        $this->artisan('cache:warm')->assertSuccessful();

        $this->assertSame(['high', 'mid', 'low'], $log->order);
    }

    public function test_succeeds_with_no_warmers_registered(): void
    {
        $this->artisan('cache:warm')->assertSuccessful();
    }
}

class SpyWarmer implements CacheWarmerInterface
{
    public bool $warmed = false;

    public function __construct(private readonly int $priority) {}

    public function warm(): void
    {
        $this->warmed = true;
    }

    public function priority(): int
    {
        return $this->priority;
    }
}

class WarmLog
{
    /** @var list<string> */
    public array $order = [];
}

class OrderTrackingWarmer implements CacheWarmerInterface
{
    public function __construct(
        private readonly int $priority,
        private readonly string $name,
        private readonly WarmLog $log,
    ) {}

    public function warm(): void
    {
        $this->log->order[] = $this->name;
    }

    public function priority(): int
    {
        return $this->priority;
    }
}
