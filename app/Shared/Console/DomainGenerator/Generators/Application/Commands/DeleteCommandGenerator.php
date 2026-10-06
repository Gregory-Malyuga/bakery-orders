<?php

namespace Shared\Console\DomainGenerator\Generators\Application\Commands;

use Illuminate\Filesystem\Filesystem;
use Shared\Console\DomainGenerator\Context\DomainContext;
use Shared\Console\DomainGenerator\Contracts\AbstractGenerator;

class DeleteCommandGenerator extends AbstractGenerator
{
    public function generate(DomainContext $ctx, Filesystem $files): void
    {
        $content = <<<PHP
        <?php

        namespace {$ctx->ns}\\Application\\Commands\\Delete;

        use Shared\\Bus\\BaseCommand;

        readonly class Delete{$ctx->name}Command implements BaseCommand
        {
            public function __construct(public int \$id) {}
        }
        PHP;

        $this->writeFile($files, "{$ctx->basePath}/Application/Commands/Delete/Delete{$ctx->name}Command.php", $content);
    }
}
