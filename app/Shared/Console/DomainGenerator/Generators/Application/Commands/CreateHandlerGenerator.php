<?php

namespace Shared\Console\DomainGenerator\Generators\Application\Commands;

use Illuminate\Filesystem\Filesystem;
use Shared\Console\DomainGenerator\Context\DomainContext;
use Shared\Console\DomainGenerator\Contracts\AbstractGenerator;

class CreateHandlerGenerator extends AbstractGenerator
{
    public function generate(DomainContext $ctx, Filesystem $files): void
    {
        $arrayItems = '';
        foreach ($ctx->fields as $fieldName => $def) {
            $arrayItems .= "                '{$fieldName}' => \$message->{$fieldName},\n";
        }

        $content = <<<PHP
        <?php

        namespace {$ctx->ns}\\Application\\Commands\\Create;

        use Shared\\Bus\\CommandHandlerInterface;
        use {$ctx->ns}\\Domain\\Events\\{$ctx->name}Created;
        use {$ctx->ns}\\Domain\\Models\\{$ctx->name};
        use {$ctx->ns}\\Application\\Repositories\\{$ctx->name}RepositoryInterface;

        readonly class Create{$ctx->name}Handler implements CommandHandlerInterface
        {
            public function __construct(private {$ctx->name}RepositoryInterface \$repository) {}

            public function handle(object \$message): int
            {
                assert(\$message instanceof Create{$ctx->name}Command);

                /** @var {$ctx->name} \$record */
                \$record = \$this->repository->create([
        {$arrayItems}        ]);

                {$ctx->name}Created::dispatch(\$record);

                return \$record->id;
            }
        }
        PHP;

        $this->writeFile($files, "{$ctx->basePath}/Application/Commands/Create/Create{$ctx->name}Handler.php", $content);
    }
}
