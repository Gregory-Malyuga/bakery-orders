<?php

namespace Shared\Console\DomainGenerator\Generators\Application\Queries;

use Illuminate\Filesystem\Filesystem;
use Shared\Console\DomainGenerator\Context\DomainContext;
use Shared\Console\DomainGenerator\Contracts\AbstractGenerator;

class FindByIdQueryGenerator extends AbstractGenerator
{
    public function generate(DomainContext $ctx, Filesystem $files): void
    {
        $content = <<<PHP
        <?php

        namespace {$ctx->ns}\\Application\\Queries\\FindById;

        use Shared\\Bus\\BaseQuery;

        readonly class Find{$ctx->name}ByIdQuery implements BaseQuery
        {
            public function __construct(public readonly int \$id) {}
        }
        PHP;

        $this->writeFile($files, "{$ctx->basePath}/Application/Queries/FindById/Find{$ctx->name}ByIdQuery.php", $content);
    }
}
