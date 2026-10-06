<?php

namespace Shared\Cache;

use Illuminate\Console\Command;
use Illuminate\Contracts\Container\Container;

class CacheWarmCommand extends Command
{
    protected $signature = 'cache:warm';

    protected $description = 'Run all registered cache warmers sorted by priority.';

    public function handle(Container $container): int
    {
        /** @var list<CacheWarmerInterface> $warmers */
        $warmers = $container->tagged('cache-warmers');

        $sorted = collect($warmers)
            ->sortByDesc(fn (CacheWarmerInterface $w) => $w->priority())
            ->values();

        if ($sorted->isEmpty()) {
            $this->info('No cache warmers registered.');

            return self::SUCCESS;
        }

        foreach ($sorted as $warmer) {
            $class = get_class($warmer);
            $this->line("Warming: {$class} (priority {$warmer->priority()})");
            $warmer->warm();
        }

        $this->info("Cache warming complete ({$sorted->count()} warmers run).");

        return self::SUCCESS;
    }
}
