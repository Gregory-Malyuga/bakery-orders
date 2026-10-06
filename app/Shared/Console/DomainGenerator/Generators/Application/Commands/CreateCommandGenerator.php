<?php

namespace Shared\Console\DomainGenerator\Generators\Application\Commands;

use Illuminate\Filesystem\Filesystem;
use Shared\Console\DomainGenerator\Context\DomainContext;
use Shared\Console\DomainGenerator\Contracts\AbstractGenerator;

class CreateCommandGenerator extends AbstractGenerator
{
    public function generate(DomainContext $ctx, Filesystem $files): void
    {
        $required = '';
        $optional = '';
        foreach ($ctx->fields as $fieldName => $def) {
            $docType = $def['phpType'] === 'array' ? 'array<string, mixed>'.($def['nullable'] ? '|null' : '') : null;
            $docLine = $docType !== null ? "        /** @param {$docType} \${$fieldName} */\n" : '';
            if ($def['nullable']) {
                $optional .= $docLine."        public ?{$def['phpType']} \${$fieldName} = null,\n";
            } else {
                $required .= $docLine."        public {$def['phpType']} \${$fieldName},\n";
            }
        }
        $params = $required.$optional;

        $content = <<<PHP
        <?php

        namespace {$ctx->ns}\\Application\\Commands\\Create;

        use Shared\\Bus\\BaseCommand;

        readonly class Create{$ctx->name}Command implements BaseCommand
        {
            public function __construct(
        {$params}    ) {}
        }
        PHP;

        $this->writeFile($files, "{$ctx->basePath}/Application/Commands/Create/Create{$ctx->name}Command.php", $content);
    }
}
