<?php

namespace Shared\Console\DomainGenerator\Generators\Application\Commands;

use Illuminate\Filesystem\Filesystem;
use Shared\Console\DomainGenerator\Context\DomainContext;
use Shared\Console\DomainGenerator\Contracts\AbstractGenerator;

class UpdateCommandGenerator extends AbstractGenerator
{
    public function generate(DomainContext $ctx, Filesystem $files): void
    {
        $required = "        public int \$id,\n";
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

        namespace {$ctx->ns}\\Application\\Commands\\Update;

        use Shared\\Bus\\BaseCommand;

        readonly class Update{$ctx->name}Command implements BaseCommand
        {
            public function __construct(
        {$params}    ) {}
        }
        PHP;

        $this->writeFile($files, "{$ctx->basePath}/Application/Commands/Update/Update{$ctx->name}Command.php", $content);
    }
}
