<?php

namespace Shared\Console\DomainGenerator\Generators\Providers;

use Illuminate\Filesystem\Filesystem;
use Shared\Console\DomainGenerator\Context\DomainContext;
use Shared\Console\DomainGenerator\Contracts\AbstractGenerator;

class ServiceProviderGenerator extends AbstractGenerator
{
    public function generate(DomainContext $ctx, Filesystem $files): void
    {
        $content = <<<PHP
        <?php

        namespace {$ctx->ns}\\Providers;

        use {$ctx->ns}\\Application\\Commands\\Create\\Create{$ctx->name}Handler;
        use {$ctx->ns}\\Application\\Commands\\Delete\\Delete{$ctx->name}Handler;
        use {$ctx->ns}\\Application\\Commands\\Update\\Update{$ctx->name}Handler;
        use {$ctx->ns}\\Application\\Queries\\FindById\\Find{$ctx->name}ByIdHandler;
        use {$ctx->ns}\\Application\\Queries\\ListAll\\List{$ctx->name}sHandler;
        use {$ctx->ns}\\Application\\Repositories\\{$ctx->name}RepositoryInterface;
        use {$ctx->ns}\\Infrastructure\\Repositories\\{$ctx->name}Repository;
        use Illuminate\\Support\\ServiceProvider;

        class {$ctx->name}ServiceProvider extends ServiceProvider
        {
            public function register(): void
            {
                \$this->app->bind(Create{$ctx->name}Handler::class);
                \$this->app->bind(Update{$ctx->name}Handler::class);
                \$this->app->bind(Delete{$ctx->name}Handler::class);
                \$this->app->bind(List{$ctx->name}sHandler::class);
                \$this->app->bind(Find{$ctx->name}ByIdHandler::class);

                \$this->app->bind({$ctx->name}RepositoryInterface::class, {$ctx->name}Repository::class);
            }

            public function boot(): void {}
        }
        PHP;

        $this->writeFile($files, "{$ctx->basePath}/Providers/{$ctx->name}ServiceProvider.php", $content);
    }
}
