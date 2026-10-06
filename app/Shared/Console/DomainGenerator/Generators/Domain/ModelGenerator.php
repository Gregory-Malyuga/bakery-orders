<?php

namespace Shared\Console\DomainGenerator\Generators\Domain;

use Illuminate\Filesystem\Filesystem;
use Shared\Console\DomainGenerator\Context\DomainContext;
use Shared\Console\DomainGenerator\Contracts\AbstractGenerator;

class ModelGenerator extends AbstractGenerator
{
    public function generate(DomainContext $ctx, Filesystem $files): void
    {
        $fillable = implode(', ', array_map(fn ($f) => "'{$f}'", array_keys($ctx->fields)));

        $castsMap = [];
        foreach ($ctx->fields as $fieldName => $def) {
            if ($def['migration'] === 'json') {
                $castsMap[$fieldName] = 'array';
            } elseif ($def['phpType'] === 'bool') {
                $castsMap[$fieldName] = 'boolean';
            }
        }
        $castsLines = implode(', ', array_map(fn ($f, $c) => "'{$f}' => '{$c}'", array_keys($castsMap), $castsMap));
        $castsProperty = $castsMap !== [] ? "\n            /** @var array<string, string> */\n            protected \$casts = [{$castsLines}];\n" : '';

        $esImports = $this->esImports($ctx);
        $esInterface = $this->esInterface($ctx);
        $esTrait = $this->esTrait($ctx);

        $content = <<<PHP
        <?php

        namespace {$ctx->ns}\\Domain\\Models;

        use {$ctx->ns}\\Domain\\Database\\Factories\\{$ctx->name}Factory;
        use Illuminate\\Database\\Eloquent\\Factories\\HasFactory;
        use Illuminate\\Database\\Eloquent\\Model;{$esImports}

        class {$ctx->name} extends Model{$esInterface}
        {{$esTrait}

            /** @use HasFactory<{$ctx->name}Factory> */
            use HasFactory;

            protected \$table = '{$ctx->table}';

            /** @var list<string> */
            protected \$fillable = [{$fillable}];{$castsProperty}

            protected static function newFactory(): {$ctx->name}Factory
            {
                return {$ctx->name}Factory::new();
            }
        }
        PHP;

        $this->writeFile($files, "{$ctx->basePath}/Domain/Models/{$ctx->name}.php", $content);
    }
}
