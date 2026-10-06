<?php

namespace Shared\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Shared\Console\DomainGenerator\Context\DomainContext;
use Shared\Console\DomainGenerator\Contracts\AbstractGenerator;
use Shared\Console\DomainGenerator\Generators\Application\Commands\CreateCommandGenerator;
use Shared\Console\DomainGenerator\Generators\Application\Commands\CreateHandlerGenerator;
use Shared\Console\DomainGenerator\Generators\Application\Commands\DeleteCommandGenerator;
use Shared\Console\DomainGenerator\Generators\Application\Commands\DeleteHandlerGenerator;
use Shared\Console\DomainGenerator\Generators\Application\Commands\UpdateCommandGenerator;
use Shared\Console\DomainGenerator\Generators\Application\Commands\UpdateHandlerGenerator;
use Shared\Console\DomainGenerator\Generators\Application\Data\CreateDataGenerator;
use Shared\Console\DomainGenerator\Generators\Application\Data\FilterDataGenerator;
use Shared\Console\DomainGenerator\Generators\Application\Data\ResourceGenerator;
use Shared\Console\DomainGenerator\Generators\Application\Data\UpdateDataGenerator;
use Shared\Console\DomainGenerator\Generators\Application\Queries\FindByIdHandlerGenerator;
use Shared\Console\DomainGenerator\Generators\Application\Queries\FindByIdQueryGenerator;
use Shared\Console\DomainGenerator\Generators\Application\Queries\ListHandlerGenerator;
use Shared\Console\DomainGenerator\Generators\Application\Queries\ListQueryGenerator;
use Shared\Console\DomainGenerator\Generators\Application\RepositoryInterfaceGenerator;
use Shared\Console\DomainGenerator\Generators\Database\MigrationGenerator;
use Shared\Console\DomainGenerator\Generators\Domain\EventGenerator;
use Shared\Console\DomainGenerator\Generators\Domain\FactoryGenerator;
use Shared\Console\DomainGenerator\Generators\Domain\ModelGenerator;
use Shared\Console\DomainGenerator\Generators\Domain\NotFoundExceptionGenerator;
use Shared\Console\DomainGenerator\Generators\Infrastructure\CacheWarmerGenerator;
use Shared\Console\DomainGenerator\Generators\Infrastructure\RepositoryGenerator;
use Shared\Console\DomainGenerator\Generators\Presentation\ControllerGenerator;
use Shared\Console\DomainGenerator\Generators\Presentation\ListRequestGenerator;
use Shared\Console\DomainGenerator\Generators\Presentation\OpenApiGenerator;
use Shared\Console\DomainGenerator\Generators\Presentation\StoreRequestGenerator;
use Shared\Console\DomainGenerator\Generators\Presentation\UpdateRequestGenerator;
use Shared\Console\DomainGenerator\Generators\Providers\ServiceProviderGenerator;
use Shared\Console\DomainGenerator\Generators\Tests\ApiTestGenerator;
use Shared\Console\DomainGenerator\Generators\Tests\RepositoryTestGenerator;
use Shared\Console\DomainGenerator\Support\FieldParser;
use Shared\Console\DomainGenerator\Support\TestValueHelper;
use Symfony\Component\Process\Process;

class MakeDomainCommand extends Command
{
    protected $signature = 'make:domain
        {name : Domain name in PascalCase, optionally nested with / (e.g. Store or Users/User)}
        {fields?* : Field definitions as field:type (e.g. name:string email:email). Omit id/created_at/updated_at.}
        {--with-cache-warmer : Generate a CacheWarmer class}
        {--with-elasticsearch : Add ElasticsearchSearchable to Repository}';

    protected $description = 'Scaffold a full working CRUD domain with CQRS, tests, routes and OpenAPI docs.';

    public function __construct(private readonly Filesystem $files)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $ctx = $this->buildContext();

        foreach ($this->generators() as $generator) {
            $generator->generate($ctx, $this->files);
        }

        $this->appendRoutes($ctx);
        $this->registerProvider($ctx);

        if (! $this->runningUnderPhpUnit()) {
            $this->call('migrate');
        }

        $this->formatGenerated($ctx);

        if (! $this->runningUnderPhpUnit() && class_exists('Barryvdh\LaravelIdeHelper\IdeHelperServiceProvider')) {
            $this->call('ide-helper:models', ['--nowrite' => true]);
        }

        $this->info("Domain <comment>{$ctx->name}</comment> generated. Register provider in bootstrap/providers.php if not done automatically.");

        return self::SUCCESS;
    }

    private function buildContext(): DomainContext
    {
        $rawInput = str_replace('\\', '/', (string) $this->argument('name'));
        $segments = array_values(array_filter(array_map(fn (string $s) => Str::studly($s), explode('/', $rawInput))));
        $name = (string) array_pop($segments);
        $parentParts = $segments;

        $domainSubPath = $parentParts ? implode('/', $parentParts).'/'.$name : $name;
        $ns = 'Domains\\'.($parentParts ? implode('\\', $parentParts).'\\' : '').$name;

        $table = Str::snake(Str::plural($name));
        $parentPath = $parentParts
            ? implode('/', array_map(fn ($p) => Str::kebab($p), $parentParts)).'/'
            : '';
        $plural = $parentPath.Str::kebab(Str::plural($name));

        /** @var list<string> $rawFields */
        $rawFields = (array) $this->argument('fields');
        $fields = (new FieldParser)->parse($rawFields);

        $basePath = base_path("app/Domains/{$domainSubPath}");

        return new DomainContext(
            name: $name,
            ns: $ns,
            table: $table,
            plural: $plural,
            fields: $fields,
            basePath: $basePath,
            unitTestPath: "{$basePath}/Tests/Unit",
            featureTestPath: "{$basePath}/Tests/Feature",
            withElasticsearch: (bool) $this->option('with-elasticsearch'),
            withCacheWarmer: (bool) $this->option('with-cache-warmer'),
        );
    }

    /** @return list<AbstractGenerator> */
    private function generators(): array
    {
        $values = new TestValueHelper;

        $outputFn = function (string $action, string $path): void {
            $rel = str_replace(base_path().DIRECTORY_SEPARATOR, '', $path);
            if ($action === 'create') {
                $this->line('<info>CREATE</info> '.$rel);
            } else {
                $this->warn('SKIP   '.$rel);
            }
        };

        $gens = [
            new ModelGenerator,
            new FactoryGenerator,
            new EventGenerator,
            new NotFoundExceptionGenerator,
            new CreateDataGenerator,
            new UpdateDataGenerator,
            new ResourceGenerator,
            new FilterDataGenerator,
            new CreateCommandGenerator,
            new CreateHandlerGenerator,
            new UpdateCommandGenerator,
            new UpdateHandlerGenerator,
            new DeleteCommandGenerator,
            new DeleteHandlerGenerator,
            new ListQueryGenerator,
            new ListHandlerGenerator,
            new FindByIdQueryGenerator,
            new FindByIdHandlerGenerator,
            new RepositoryInterfaceGenerator,
            new RepositoryGenerator,
            new CacheWarmerGenerator,
            new ControllerGenerator,
            new ListRequestGenerator,
            new StoreRequestGenerator,
            new UpdateRequestGenerator,
            new OpenApiGenerator,
            new ServiceProviderGenerator,
            new MigrationGenerator,
            new RepositoryTestGenerator($values),
            new ApiTestGenerator($values),
        ];

        return array_map(fn (AbstractGenerator $g): AbstractGenerator => $g->withOutput($outputFn), $gens);
    }

    private function appendRoutes(DomainContext $ctx): void
    {
        $file = base_path('routes/api.php');

        if (! $this->files->exists($file)) {
            $this->warn('routes/api.php not found — skipping route registration');

            return;
        }

        $content = $this->files->get($file);
        $controllerFqcn = "{$ctx->ns}\\Presentation\\Http\\Controllers\\{$ctx->name}Controller";

        if (str_contains($content, $controllerFqcn)) {
            $this->warn('SKIP   routes already registered: '.$ctx->plural);

            return;
        }

        $routeName = str_replace('/', '.', $ctx->plural);

        $block = <<<PHP


        Route::prefix('v1/{$ctx->plural}')->middleware(['auth:sanctum'])->group(function (): void {
            Route::get('/', [\\{$controllerFqcn}::class, 'index'])->name('api.v1.{$routeName}.index');
            Route::post('/', [\\{$controllerFqcn}::class, 'store'])->name('api.v1.{$routeName}.store');
            Route::get('/{id}', [\\{$controllerFqcn}::class, 'show'])->name('api.v1.{$routeName}.show');
            Route::put('/{id}', [\\{$controllerFqcn}::class, 'update'])->name('api.v1.{$routeName}.update');
            Route::delete('/{id}', [\\{$controllerFqcn}::class, 'destroy'])->name('api.v1.{$routeName}.destroy');
        });
        PHP;

        $this->files->append($file, $block);
        $this->line('<info>ROUTE </info> Added routes for '.$ctx->plural);
    }

    private function registerProvider(DomainContext $ctx): void
    {
        $providerClass = "{$ctx->ns}\\Providers\\{$ctx->name}ServiceProvider";
        $file = base_path('bootstrap/providers.php');
        $content = $this->files->get($file);

        if (str_contains($content, $providerClass.'::class')) {
            $this->warn('SKIP   provider already registered: '.$providerClass);

            return;
        }

        $pos = strrpos($content, '];');
        if ($pos === false) {
            $this->warn('Could not locate closing ]; in bootstrap/providers.php — skipping provider registration');

            return;
        }

        $content = substr($content, 0, $pos)."    {$providerClass}::class,\n];".substr($content, $pos + 2);

        $this->files->put($file, $content);
        $this->line('<info>PROVIDER</info> Registered '.$providerClass);
    }

    private function runningUnderPhpUnit(): bool
    {
        // class_exists with autoload=false: true only when PHPUnit is already loaded in memory,
        // which only happens during actual test runs regardless of APP_ENV value.
        return class_exists('PHPUnit\Framework\TestCase', false);
    }

    private function formatGenerated(DomainContext $ctx): void
    {
        if ($this->runningUnderPhpUnit()) {
            return;
        }

        $pint = base_path('vendor/bin/pint');
        if (! $this->files->exists($pint)) {
            return;
        }

        $migGlob = database_path("migrations/*_create_{$ctx->table}_table.php");

        $domainExists = $this->files->isDirectory($ctx->basePath) ? [$ctx->basePath] : [];
        $migFiles = $this->files->glob($migGlob);
        $all = array_merge($domainExists, $migFiles);

        if (empty($all)) {
            return;
        }

        /** @var list<string> $all */
        (new Process([$pint, ...$all]))->run();
    }
}
