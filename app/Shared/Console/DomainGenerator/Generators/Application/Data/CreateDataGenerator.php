<?php

namespace Shared\Console\DomainGenerator\Generators\Application\Data;

use Illuminate\Filesystem\Filesystem;
use Shared\Console\DomainGenerator\Context\DomainContext;
use Shared\Console\DomainGenerator\Contracts\AbstractGenerator;

class CreateDataGenerator extends AbstractGenerator
{
    public function generate(DomainContext $ctx, Filesystem $files): void
    {
        $required = '';
        $optional = '';
        $rules = '';

        foreach ($ctx->fields as $fieldName => $def) {
            $docPrefix = $def['phpType'] === 'array'
                ? '        /** @var array<string, mixed>'.($def['nullable'] ? '|null' : '')." */\n"
                : '';
            if ($def['nullable']) {
                $optional .= $docPrefix."        public readonly ?{$def['phpType']} \${$fieldName} = null,\n";
            } else {
                $required .= $docPrefix."        public readonly {$def['phpType']} \${$fieldName},\n";
            }

            $ruleList = implode("', '", $def['rules']);
            $rules .= "            '{$fieldName}' => ['{$ruleList}'],\n";
        }

        $props = $required.$optional;

        $content = <<<PHP
        <?php

        namespace {$ctx->ns}\\Application\\Data;

        use Shared\\Http\\Data\\BaseData;

        class Create{$ctx->name}Data extends BaseData
        {
            public function __construct(
        {$props}    ) {}

            /** @return array<string, list<string>> */
            public static function rules(): array
            {
                return [
        {$rules}        ];
            }
        }
        PHP;

        $this->writeFile($files, "{$ctx->basePath}/Application/Data/Create{$ctx->name}Data.php", $content);
    }
}
