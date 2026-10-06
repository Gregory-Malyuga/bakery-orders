<?php

namespace Shared\Console\DomainGenerator\Generators\Presentation;

use Illuminate\Filesystem\Filesystem;
use Shared\Console\DomainGenerator\Context\DomainContext;
use Shared\Console\DomainGenerator\Contracts\AbstractGenerator;

class ListRequestGenerator extends AbstractGenerator
{
    public function generate(DomainContext $ctx, Filesystem $files): void
    {
        $content = <<<PHP
        <?php

        namespace {$ctx->ns}\\Presentation\\Http\\Requests;

        use {$ctx->ns}\\Application\\Data\\{$ctx->name}FilterData;
        use {$ctx->ns}\\Application\\Queries\\ListAll\\List{$ctx->name}sQuery;
        use Shared\\Http\\Requests\\ListRequest;

        class List{$ctx->name}sRequest extends ListRequest
        {
            protected const array SORTABLE = List{$ctx->name}sQuery::SORTABLE;

            public function toFilters(): {$ctx->name}FilterData
            {
                return {$ctx->name}FilterData::from(\$this->all());
            }
        }
        PHP;

        $this->writeFile($files, "{$ctx->basePath}/Presentation/Http/Requests/List{$ctx->name}sRequest.php", $content);
    }
}
