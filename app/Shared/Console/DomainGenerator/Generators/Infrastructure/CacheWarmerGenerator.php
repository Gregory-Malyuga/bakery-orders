<?php

namespace Shared\Console\DomainGenerator\Generators\Infrastructure;

use Illuminate\Filesystem\Filesystem;
use Shared\Console\DomainGenerator\Context\DomainContext;
use Shared\Console\DomainGenerator\Contracts\AbstractGenerator;

class CacheWarmerGenerator extends AbstractGenerator
{
    public function generate(DomainContext $ctx, Filesystem $files): void
    {
        if (! $ctx->withCacheWarmer) {
            return;
        }

        $content = <<<PHP
        <?php

        namespace {$ctx->ns}\\Infrastructure\\Cache;

        use Shared\\Cache\\CacheWarmerInterface;

        class {$ctx->name}CacheWarmer implements CacheWarmerInterface
        {
            public function warm(): void {}

            public function priority(): int
            {
                return 10;
            }
        }
        PHP;

        $this->writeFile($files, "{$ctx->basePath}/Infrastructure/Cache/{$ctx->name}CacheWarmer.php", $content);
    }
}
