<?php

namespace Shared\Console\DomainGenerator\Generators\Domain;

use Illuminate\Filesystem\Filesystem;
use Shared\Console\DomainGenerator\Context\DomainContext;
use Shared\Console\DomainGenerator\Contracts\AbstractGenerator;

class FactoryGenerator extends AbstractGenerator
{
    public function generate(DomainContext $ctx, Filesystem $files): void
    {
        $definition = '';
        foreach ($ctx->fields as $fieldName => $def) {
            $definition .= "            '{$fieldName}' => {$def['faker']},\n";
        }

        $content = <<<PHP
        <?php

        namespace {$ctx->ns}\\Domain\\Database\\Factories;

        use {$ctx->ns}\\Domain\\Models\\{$ctx->name};
        use Illuminate\\Database\\Eloquent\\Factories\\Factory;

        /**
         * @extends Factory<{$ctx->name}>
         */
        class {$ctx->name}Factory extends Factory
        {
            protected \$model = {$ctx->name}::class;

            /** @return array<string, mixed> */
            public function definition(): array
            {
                return [
        {$definition}        ];
            }
        }
        PHP;

        $this->writeFile($files, "{$ctx->basePath}/Domain/Database/Factories/{$ctx->name}Factory.php", $content);
    }
}
