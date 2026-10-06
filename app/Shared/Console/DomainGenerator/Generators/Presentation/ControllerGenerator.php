<?php

namespace Shared\Console\DomainGenerator\Generators\Presentation;

use Illuminate\Filesystem\Filesystem;
use Shared\Console\DomainGenerator\Context\DomainContext;
use Shared\Console\DomainGenerator\Contracts\AbstractGenerator;

class ControllerGenerator extends AbstractGenerator
{
    public function generate(DomainContext $ctx, Filesystem $files): void
    {
        $args = '';
        foreach ($ctx->fields as $fieldName => $def) {
            $args .= "                {$fieldName}: \$dto->{$fieldName},\n";
        }

        $content = <<<PHP
        <?php

        namespace {$ctx->ns}\\Presentation\\Http\\Controllers;

        use Illuminate\\Contracts\\Pagination\\LengthAwarePaginator;
        use Illuminate\\Http\\JsonResponse;

        use Illuminate\\Routing\\Controller;
        use Shared\\Bus\\CommandBusInterface;
        use Shared\\Bus\\QueryBusInterface;
        use Spatie\\LaravelData\\PaginatedDataCollection;
        use {$ctx->ns}\\Application\\Commands\\Create\\Create{$ctx->name}Command;
        use {$ctx->ns}\\Application\\Commands\\Delete\\Delete{$ctx->name}Command;
        use {$ctx->ns}\\Application\\Commands\\Update\\Update{$ctx->name}Command;
        use {$ctx->ns}\\Application\\Data\\Create{$ctx->name}Data;
        use {$ctx->ns}\\Application\\Data\\Update{$ctx->name}Data;
        use {$ctx->ns}\\Application\\Data\\{$ctx->name}Resource;
        use {$ctx->ns}\\Application\\Queries\\FindById\\Find{$ctx->name}ByIdQuery;
        use {$ctx->ns}\\Application\\Queries\\ListAll\\List{$ctx->name}sQuery;
        use {$ctx->ns}\\Domain\\Models\\{$ctx->name};
        use {$ctx->ns}\\Presentation\\Http\\Requests\\List{$ctx->name}sRequest;
        use {$ctx->ns}\\Presentation\\Http\\Requests\\Store{$ctx->name}Request;
        use {$ctx->ns}\\Presentation\\Http\\Requests\\Update{$ctx->name}Request;

        class {$ctx->name}Controller extends Controller
        {
            public function __construct(
                private readonly CommandBusInterface \$commands,
                private readonly QueryBusInterface \$queries,
            ) {}

            public function index(List{$ctx->name}sRequest \$request): JsonResponse
            {
                /** @var LengthAwarePaginator<int, {$ctx->name}> \$paginator */
                \$paginator = \$this->queries->ask(new List{$ctx->name}sQuery(
                    filters: \$request->toFilters(),
                    sort: \$request->toSort(),
                    pagination: \$request->toPagination(),
                ));

                return response()->json({$ctx->name}Resource::collect(\$paginator, PaginatedDataCollection::class));
            }

            public function show(int \$id): JsonResponse
            {
                /** @var {$ctx->name} \$record */
                \$record = \$this->queries->ask(new Find{$ctx->name}ByIdQuery(\$id));

                return response()->json({$ctx->name}Resource::from(\$record));
            }

            public function store(Store{$ctx->name}Request \$request): JsonResponse
            {
                \$dto = Create{$ctx->name}Data::from(\$request);

                /** @var int \$id */
                \$id = \$this->commands->dispatch(new Create{$ctx->name}Command(
        {$args}        ));

                /** @var {$ctx->name} \$record */
                \$record = \$this->queries->ask(new Find{$ctx->name}ByIdQuery(\$id));

                return response()->json({$ctx->name}Resource::from(\$record), 201);
            }

            public function update(int \$id, Update{$ctx->name}Request \$request): JsonResponse
            {
                \$dto = Update{$ctx->name}Data::from(\$request);

                \$this->commands->dispatch(new Update{$ctx->name}Command(
                    id: \$id,
        {$args}        ));

                /** @var {$ctx->name} \$record */
                \$record = \$this->queries->ask(new Find{$ctx->name}ByIdQuery(\$id));

                return response()->json({$ctx->name}Resource::from(\$record));
            }

            public function destroy(int \$id): JsonResponse
            {
                \$this->commands->dispatch(new Delete{$ctx->name}Command(\$id));

                return response()->json(null, 204);
            }
        }
        PHP;

        $this->writeFile($files, "{$ctx->basePath}/Presentation/Http/Controllers/{$ctx->name}Controller.php", $content);
    }
}
