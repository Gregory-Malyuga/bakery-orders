<?php

namespace Shared\Console\DomainGenerator\Generators\Domain;

use Illuminate\Filesystem\Filesystem;
use Shared\Console\DomainGenerator\Context\DomainContext;
use Shared\Console\DomainGenerator\Contracts\AbstractGenerator;

class NotFoundExceptionGenerator extends AbstractGenerator
{
    public function generate(DomainContext $ctx, Filesystem $files): void
    {
        $content = <<<PHP
        <?php

        namespace {$ctx->ns}\\Domain\\Exceptions;

        use RuntimeException;

        final class {$ctx->name}NotFoundException extends RuntimeException
        {
            public static function forId(int|string \$id): self
            {
                return new self("{$ctx->name} not found: {\$id}");
            }
        }
        PHP;

        $this->writeFile($files, "{$ctx->basePath}/Domain/Exceptions/{$ctx->name}NotFoundException.php", $content);
    }
}
