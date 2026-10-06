<?php

namespace Shared\Console\DomainGenerator\Generators\Infrastructure;

use Illuminate\Filesystem\Filesystem;
use Shared\Console\DomainGenerator\Context\DomainContext;
use Shared\Console\DomainGenerator\Contracts\AbstractGenerator;

class RepositoryGenerator extends AbstractGenerator
{
    public function generate(DomainContext $ctx, Filesystem $files): void
    {
        $esImports = $this->esImports($ctx);
        $esTrait = $this->esTrait($ctx);

        $implements = $ctx->withElasticsearch
            ? " implements ElasticsearchSearchable, {$ctx->name}RepositoryInterface"
            : " implements {$ctx->name}RepositoryInterface";

        $content = <<<PHP
        <?php

        namespace {$ctx->ns}\\Infrastructure\\Repositories;

        use Shared\\Repository\\BaseRepository;
        use {$ctx->ns}\\Application\\Repositories\\{$ctx->name}RepositoryInterface;
        use {$ctx->ns}\\Domain\\Models\\{$ctx->name};{$esImports}

        class {$ctx->name}Repository extends BaseRepository{$implements}
        {{$esTrait}
            protected string \$model = {$ctx->name}::class;

            public function findOrFail(int|string \$id): {$ctx->name}
            {
                /** @var {$ctx->name} */
                return parent::findOrFail(\$id);
            }

            /** @param array<string, mixed> \$data */
            public function create(array \$data): {$ctx->name}
            {
                /** @var {$ctx->name} */
                return parent::create(\$data);
            }

            /** @param array<string, mixed> \$data */
            public function update(\Illuminate\Database\Eloquent\Model \$model, array \$data): {$ctx->name}
            {
                /** @var {$ctx->name} */
                return parent::update(\$model, \$data);
            }
        }
        PHP;

        $this->writeFile($files, "{$ctx->basePath}/Infrastructure/Repositories/{$ctx->name}Repository.php", $content);
    }
}
