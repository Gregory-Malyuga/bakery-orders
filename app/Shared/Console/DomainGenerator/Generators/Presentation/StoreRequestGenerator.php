<?php

namespace Shared\Console\DomainGenerator\Generators\Presentation;

use Illuminate\Filesystem\Filesystem;
use Shared\Console\DomainGenerator\Context\DomainContext;
use Shared\Console\DomainGenerator\Contracts\AbstractGenerator;

class StoreRequestGenerator extends AbstractGenerator
{
    public function generate(DomainContext $ctx, Filesystem $files): void
    {
        $content = <<<PHP
        <?php

        namespace {$ctx->ns}\\Presentation\\Http\\Requests;

        use {$ctx->ns}\\Application\\Data\\Create{$ctx->name}Data;
        use Illuminate\\Foundation\\Http\\FormRequest;

        class Store{$ctx->name}Request extends FormRequest
        {
            public function authorize(): bool
            {
                return true;
            }

            /** @return array<string, list<mixed>> */
            public function rules(): array
            {
                return Create{$ctx->name}Data::rules();
            }
        }
        PHP;

        $this->writeFile($files, "{$ctx->basePath}/Presentation/Http/Requests/Store{$ctx->name}Request.php", $content);
    }
}
