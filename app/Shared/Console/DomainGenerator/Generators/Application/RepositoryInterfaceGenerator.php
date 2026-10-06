<?php

namespace Shared\Console\DomainGenerator\Generators\Application;

use Illuminate\Filesystem\Filesystem;
use Shared\Console\DomainGenerator\Context\DomainContext;
use Shared\Console\DomainGenerator\Contracts\AbstractGenerator;

class RepositoryInterfaceGenerator extends AbstractGenerator
{
    public function generate(DomainContext $ctx, Filesystem $files): void
    {
        $content = <<<PHP
        <?php

        namespace {$ctx->ns}\\Application\\Repositories;

        use {$ctx->ns}\\Domain\\Models\\{$ctx->name};
        use Illuminate\\Contracts\\Pagination\\LengthAwarePaginator;
        use Shared\\Data\\PaginationData;
        use Shared\\Data\\SortData;
        use Spatie\\LaravelData\\Data;

        interface {$ctx->name}RepositoryInterface
        {
            public function findOrFail(int|string \$id): {$ctx->name};

            /** @param array<string, mixed> \$data */
            public function create(array \$data): {$ctx->name};

            /** @param array<string, mixed> \$data */
            public function update({$ctx->name} \$record, array \$data): {$ctx->name};

            public function delete({$ctx->name} \$record): void;

            /** @return LengthAwarePaginator<int, {$ctx->name}> */
            public function list(Data \$filters, ?SortData \$sort = null, ?PaginationData \$pagination = null): LengthAwarePaginator;
        }
        PHP;

        $this->writeFile($files, "{$ctx->basePath}/Application/Repositories/{$ctx->name}RepositoryInterface.php", $content);
    }
}
