<?php

namespace Shared\Console\DomainGenerator\Generators\Application\Queries;

use Illuminate\Filesystem\Filesystem;
use Shared\Console\DomainGenerator\Context\DomainContext;
use Shared\Console\DomainGenerator\Contracts\AbstractGenerator;

class ListHandlerGenerator extends AbstractGenerator
{
    public function generate(DomainContext $ctx, Filesystem $files): void
    {
        $content = <<<PHP
        <?php

        namespace {$ctx->ns}\\Application\\Queries\\ListAll;

        use Shared\\Bus\\QueryHandlerInterface;
        use {$ctx->ns}\\Domain\\Models\\{$ctx->name};
        use {$ctx->ns}\\Application\\Repositories\\{$ctx->name}RepositoryInterface;
        use Illuminate\\Contracts\\Pagination\\LengthAwarePaginator;

        readonly class List{$ctx->name}sHandler implements QueryHandlerInterface
        {
            public function __construct(private readonly {$ctx->name}RepositoryInterface \$repository) {}

            /** @return LengthAwarePaginator<int, {$ctx->name}> */
            public function handle(object \$message): LengthAwarePaginator
            {
                assert(\$message instanceof List{$ctx->name}sQuery);

                /** @var LengthAwarePaginator<int, {$ctx->name}> \$result */
                \$result = \$this->repository->list(
                    \$message->filters,
                    \$message->sort,
                    \$message->pagination,
                );

                return \$result;
            }
        }
        PHP;

        $this->writeFile($files, "{$ctx->basePath}/Application/Queries/ListAll/List{$ctx->name}sHandler.php", $content);
    }
}
