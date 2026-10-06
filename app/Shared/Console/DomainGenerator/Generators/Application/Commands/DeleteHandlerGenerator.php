<?php

namespace Shared\Console\DomainGenerator\Generators\Application\Commands;

use Illuminate\Filesystem\Filesystem;
use Shared\Console\DomainGenerator\Context\DomainContext;
use Shared\Console\DomainGenerator\Contracts\AbstractGenerator;

class DeleteHandlerGenerator extends AbstractGenerator
{
    public function generate(DomainContext $ctx, Filesystem $files): void
    {
        $content = <<<PHP
        <?php

        namespace {$ctx->ns}\\Application\\Commands\\Delete;

        use Shared\\Bus\\CommandHandlerInterface;
        use {$ctx->ns}\\Domain\\Events\\{$ctx->name}Deleted;
        use {$ctx->ns}\\Application\\Repositories\\{$ctx->name}RepositoryInterface;

        readonly class Delete{$ctx->name}Handler implements CommandHandlerInterface
        {
            public function __construct(private {$ctx->name}RepositoryInterface \$repository) {}

            public function handle(object \$message): null
            {
                assert(\$message instanceof Delete{$ctx->name}Command);

                \$record = \$this->repository->findOrFail(\$message->id);
                \$this->repository->delete(\$record);
                {$ctx->name}Deleted::dispatch(\$record);

                return null;
            }
        }
        PHP;

        $this->writeFile($files, "{$ctx->basePath}/Application/Commands/Delete/Delete{$ctx->name}Handler.php", $content);
    }
}
