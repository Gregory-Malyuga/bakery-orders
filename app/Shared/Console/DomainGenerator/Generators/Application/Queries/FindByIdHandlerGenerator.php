<?php

namespace Shared\Console\DomainGenerator\Generators\Application\Queries;

use Illuminate\Filesystem\Filesystem;
use Shared\Console\DomainGenerator\Context\DomainContext;
use Shared\Console\DomainGenerator\Contracts\AbstractGenerator;

class FindByIdHandlerGenerator extends AbstractGenerator
{
    public function generate(DomainContext $ctx, Filesystem $files): void
    {
        $content = <<<PHP
        <?php

        namespace {$ctx->ns}\\Application\\Queries\\FindById;

        use Shared\\Bus\\QueryHandlerInterface;
        use {$ctx->ns}\\Domain\\Models\\{$ctx->name};
        use {$ctx->ns}\\Application\\Repositories\\{$ctx->name}RepositoryInterface;

        readonly class Find{$ctx->name}ByIdHandler implements QueryHandlerInterface
        {
            public function __construct(private readonly {$ctx->name}RepositoryInterface \$repository) {}

            public function handle(object \$message): {$ctx->name}
            {
                assert(\$message instanceof Find{$ctx->name}ByIdQuery);

                /** @var {$ctx->name} \$record */
                \$record = \$this->repository->findOrFail(\$message->id);

                return \$record;
            }
        }
        PHP;

        $this->writeFile($files, "{$ctx->basePath}/Application/Queries/FindById/Find{$ctx->name}ByIdHandler.php", $content);
    }
}
