<?php

namespace Shared\Console\DomainGenerator\Generators\Application\Queries;

use Illuminate\Filesystem\Filesystem;
use Shared\Console\DomainGenerator\Context\DomainContext;
use Shared\Console\DomainGenerator\Contracts\AbstractGenerator;

class ListQueryGenerator extends AbstractGenerator
{
    public function generate(DomainContext $ctx, Filesystem $files): void
    {
        $content = <<<PHP
        <?php

        namespace {$ctx->ns}\\Application\\Queries\\ListAll;

        use Shared\\Bus\\ListEntityQuery;

        class List{$ctx->name}sQuery extends ListEntityQuery
        {
            /** @var list<string> */
            public const array SORTABLE = ['id'];
        }
        PHP;

        $this->writeFile($files, "{$ctx->basePath}/Application/Queries/ListAll/List{$ctx->name}sQuery.php", $content);
    }
}
