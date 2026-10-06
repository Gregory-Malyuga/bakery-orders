<?php

namespace Shared\Console\DomainGenerator\Generators\Application\Data;

use Illuminate\Filesystem\Filesystem;
use Shared\Console\DomainGenerator\Context\DomainContext;
use Shared\Console\DomainGenerator\Contracts\AbstractGenerator;

class FilterDataGenerator extends AbstractGenerator
{
    public function generate(DomainContext $ctx, Filesystem $files): void
    {
        $props = '';
        foreach ($ctx->fields as $fieldName => $def) {
            $docPrefix = $def['phpType'] === 'array' ? "        /** @var array<string, mixed>|null */\n" : '';
            $props .= $docPrefix."        public readonly ?{$def['phpType']} \${$fieldName} = null,\n";
        }

        $content = <<<PHP
        <?php

        namespace {$ctx->ns}\\Application\\Data;

        use Spatie\\LaravelData\\Data;

        class {$ctx->name}FilterData extends Data
        {
            public function __construct(
        {$props}    ) {}
        }
        PHP;

        $this->writeFile($files, "{$ctx->basePath}/Application/Data/{$ctx->name}FilterData.php", $content);
    }
}
