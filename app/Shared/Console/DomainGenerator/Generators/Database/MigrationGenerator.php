<?php

namespace Shared\Console\DomainGenerator\Generators\Database;

use Illuminate\Filesystem\Filesystem;
use Shared\Console\DomainGenerator\Context\DomainContext;
use Shared\Console\DomainGenerator\Contracts\AbstractGenerator;

class MigrationGenerator extends AbstractGenerator
{
    public function generate(DomainContext $ctx, Filesystem $files): void
    {
        $existing = $files->glob(database_path("migrations/*_create_{$ctx->table}_table.php"));
        if (! empty($existing)) {
            if ($this->outputFn !== null) {
                ($this->outputFn)('skip', $existing[0]);
            }

            return;
        }

        $columns = '';
        foreach ($ctx->fields as $fieldName => $def) {
            $nullable = $def['nullable'] ? '->nullable()' : '';
            if ($def['migration'] === 'decimal') {
                $columns .= "            \$table->decimal('{$fieldName}', 10, 2){$nullable};\n";
            } elseif ($def['migration'] === 'string') {
                $columns .= "            \$table->string('{$fieldName}'){$nullable};\n";
            } else {
                $columns .= "            \$table->{$def['migration']}('{$fieldName}'){$nullable};\n";
            }
        }

        $table = $ctx->table;

        $content = <<<PHP
        <?php

        use Illuminate\\Database\\Migrations\\Migration;
        use Illuminate\\Database\\Schema\\Blueprint;
        use Illuminate\\Support\\Facades\\Schema;

        return new class extends Migration
        {
            public function up(): void
            {
                Schema::create('{$table}', function (Blueprint \$table): void {
                    \$table->id();
        {$columns}            \$table->timestamps();
                });
            }

            public function down(): void
            {
                Schema::dropIfExists('{$table}');
            }
        };
        PHP;

        $timestamp = now()->format('Y_m_d_His');
        $path = database_path("migrations/{$timestamp}_create_{$table}_table.php");
        $this->writeFile($files, $path, $content);
    }
}
