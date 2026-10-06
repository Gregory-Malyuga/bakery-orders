<?php

namespace Shared\Console\DomainGenerator\Generators\Tests;

use Illuminate\Filesystem\Filesystem;
use Shared\Console\DomainGenerator\Context\DomainContext;
use Shared\Console\DomainGenerator\Contracts\AbstractGenerator;
use Shared\Console\DomainGenerator\Support\TestValueHelper;

class RepositoryTestGenerator extends AbstractGenerator
{
    public function __construct(private readonly TestValueHelper $values) {}

    public function generate(DomainContext $ctx, Filesystem $files): void
    {
        $makeData = '';
        $updateData = '';
        $filterTests = '';

        foreach ($ctx->fields as $fieldName => $def) {
            $val = $this->values->valueFor($fieldName, $def['phpType']);
            $updateVal = $this->values->valueFor($fieldName, $def['phpType'], true);
            $makeData .= "            '{$fieldName}' => {$val},\n";
            $updateData .= "            '{$fieldName}' => {$updateVal},\n";

            if (in_array($def['phpType'], ['string', 'int'], true)) {
                $filterTests .= $this->filterTestMethod($ctx, $fieldName, $def['phpType']);
            }
        }

        $content = <<<PHP
        <?php

        namespace {$ctx->ns}\\Tests\\Unit;

        use Shared\\Repository\\BaseRepository;
        use Shared\\Testing\\BaseRepositoryTest;
        use {$ctx->ns}\\Infrastructure\\Repositories\\{$ctx->name}Repository;

        class {$ctx->name}RepositoryTest extends BaseRepositoryTest
        {
            protected function repository(): BaseRepository
            {
                return new {$ctx->name}Repository;
            }

            /** @return array<string, mixed> */
            protected function makeModelData(): array
            {
                return [
        {$makeData}        ];
            }

            /** @return array<string, mixed> */
            protected function updateModelData(): array
            {
                return [
        {$updateData}        ];
            }
        {$filterTests}}
        PHP;

        $this->writeFile($files, "{$ctx->unitTestPath}/{$ctx->name}RepositoryTest.php", $content);
    }

    private function filterTestMethod(DomainContext $ctx, string $fieldName, string $phpType): string
    {
        $val1 = $this->values->valueFor($fieldName, $phpType);
        $val2 = $this->values->valueFor($fieldName, $phpType, true);
        $methodName = 'test_list_filters_by_'.$fieldName;

        return <<<PHP

            public function {$methodName}(): void
            {
                \$this->repository()->create(array_merge(\$this->makeModelData(), ['{$fieldName}' => {$val1}]));
                \$this->repository()->create(array_merge(\$this->makeModelData(), ['{$fieldName}' => {$val2}]));

                \$filters = new \\{$ctx->ns}\\Application\\Data\\{$ctx->name}FilterData({$fieldName}: {$val1});
                \$result = \$this->repository()->list(\$filters);

                \$this->assertSame(1, \$result->total());
            }

        PHP;
    }
}
