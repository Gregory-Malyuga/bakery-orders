<?php

namespace Shared\Console\DomainGenerator\Contracts;

use Illuminate\Filesystem\Filesystem;
use Shared\Console\DomainGenerator\Context\DomainContext;

abstract class AbstractGenerator implements GeneratorInterface
{
    protected ?\Closure $outputFn = null;

    public function withOutput(\Closure $fn): static
    {
        $this->outputFn = $fn;

        return $this;
    }

    protected function writeFile(Filesystem $files, string $path, string $content): void
    {
        $files->ensureDirectoryExists(dirname($path));

        if ($files->exists($path)) {
            if ($this->outputFn !== null) {
                ($this->outputFn)('skip', $path);
            }

            return;
        }

        $files->put($path, $content);

        if ($this->outputFn !== null) {
            ($this->outputFn)('create', $path);
        }
    }

    protected function esImports(DomainContext $ctx): string
    {
        return $ctx->withElasticsearch
            ? "\nuse Shared\\Elasticsearch\\ElasticsearchSearchable;\nuse Shared\\Elasticsearch\\InteractsWithElasticsearch;"
            : '';
    }

    protected function esInterface(DomainContext $ctx): string
    {
        return $ctx->withElasticsearch ? ' implements ElasticsearchSearchable' : '';
    }

    protected function esTrait(DomainContext $ctx): string
    {
        return $ctx->withElasticsearch ? "\n    use InteractsWithElasticsearch;\n" : '';
    }
}
