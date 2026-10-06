<?php

namespace Shared\Console\DomainGenerator\Generators\Application\Commands;

use Illuminate\Filesystem\Filesystem;
use Shared\Console\DomainGenerator\Context\DomainContext;
use Shared\Console\DomainGenerator\Contracts\AbstractGenerator;

class UpdateHandlerGenerator extends AbstractGenerator
{
    public function generate(DomainContext $ctx, Filesystem $files): void
    {
        $arrayItems = '';
        foreach ($ctx->fields as $fieldName => $def) {
            $arrayItems .= "                '{$fieldName}' => \$message->{$fieldName},\n";
        }

        $content = <<<PHP
        <?php

        namespace {$ctx->ns}\\Application\\Commands\\Update;

        use Shared\\Bus\\CommandHandlerInterface;
        use {$ctx->ns}\\Domain\\Events\\{$ctx->name}Updated;
        use {$ctx->ns}\\Domain\\Models\\{$ctx->name};
        use {$ctx->ns}\\Application\\Repositories\\{$ctx->name}RepositoryInterface;

        readonly class Update{$ctx->name}Handler implements CommandHandlerInterface
        {
            public function __construct(private {$ctx->name}RepositoryInterface \$repository) {}

            public function handle(object \$message): null
            {
                assert(\$message instanceof Update{$ctx->name}Command);

                /** @var {$ctx->name} \$record */
                \$record = \$this->repository->findOrFail(\$message->id);
                \$data = array_filter([
        {$arrayItems}        ], static fn (mixed \$v): bool => \$v !== null);
                \$updated = \$this->repository->update(\$record, \$data);

                {$ctx->name}Updated::dispatch(\$updated);

                return null;
            }
        }
        PHP;

        $this->writeFile($files, "{$ctx->basePath}/Application/Commands/Update/Update{$ctx->name}Handler.php", $content);
    }
}
