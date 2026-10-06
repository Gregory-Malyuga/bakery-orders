<?php

namespace Shared\Console\DomainGenerator\Generators\Application\Data;

use Illuminate\Filesystem\Filesystem;
use Shared\Console\DomainGenerator\Context\DomainContext;
use Shared\Console\DomainGenerator\Contracts\AbstractGenerator;

class ResourceGenerator extends AbstractGenerator
{
    public function generate(DomainContext $ctx, Filesystem $files): void
    {
        $required = "        public readonly int \$id,\n";
        $optional = '';

        foreach ($ctx->fields as $fieldName => $def) {
            $docPrefix = $def['phpType'] === 'array'
                ? '        /** @var array<string, mixed>'.($def['nullable'] ? '|null' : '')." */\n"
                : '';
            if ($def['nullable']) {
                $optional .= $docPrefix."        public readonly ?{$def['phpType']} \${$fieldName} = null,\n";
            } else {
                $required .= $docPrefix."        public readonly {$def['phpType']} \${$fieldName},\n";
            }
        }

        $optional .= "        public readonly ?string \$created_at = null,\n";
        $optional .= "        public readonly ?string \$updated_at = null,\n";
        $props = $required.$optional;

        $content = <<<PHP
        <?php

        namespace {$ctx->ns}\\Application\\Data;

        use Shared\\Http\\Data\\BaseData;

        class {$ctx->name}Resource extends BaseData
        {
            public function __construct(
        {$props}    ) {}
        }
        PHP;

        $this->writeFile($files, "{$ctx->basePath}/Application/Data/{$ctx->name}Resource.php", $content);
    }
}
