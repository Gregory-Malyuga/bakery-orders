<?php

namespace Shared\Console\DomainGenerator\Generators\Tests;

use Illuminate\Filesystem\Filesystem;
use Shared\Console\DomainGenerator\Context\DomainContext;
use Shared\Console\DomainGenerator\Contracts\AbstractGenerator;
use Shared\Console\DomainGenerator\Support\TestValueHelper;

class ApiTestGenerator extends AbstractGenerator
{
    public function __construct(private readonly TestValueHelper $values) {}

    public function generate(DomainContext $ctx, Filesystem $files): void
    {
        $storePayload = '';
        $updatePayload = '';
        $filterTests = '';
        $firstStringField = '';
        $firstStringValue = '';
        $firstStringValue2 = '';

        foreach ($ctx->fields as $fieldName => $def) {
            $val = $this->values->valueFor($fieldName, $def['phpType']);
            $updateVal = $this->values->valueFor($fieldName, $def['phpType'], true);
            $storePayload .= "            '{$fieldName}' => {$val},\n";
            $updatePayload .= "            '{$fieldName}' => {$updateVal},\n";

            if ($firstStringField === '' && $def['phpType'] === 'string') {
                $firstStringField = $fieldName;
                $firstStringValue = trim($val, "'");
                $firstStringValue2 = trim($updateVal, "'");
            }
        }

        if ($firstStringField !== '') {
            $filterTests = $this->apiFilterTestMethod($ctx, $firstStringField, $firstStringValue, $firstStringValue2);
        }

        $plural = $ctx->plural;

        $content = <<<PHP
        <?php

        namespace {$ctx->ns}\\Tests\\Feature;

        use Shared\\Testing\\BaseApiTest;
        use {$ctx->ns}\\Domain\\Models\\{$ctx->name};
        use Illuminate\\Database\\Eloquent\\Model;

        class {$ctx->name}ApiTest extends BaseApiTest
        {
            protected function basePath(): string
            {
                return '/api/v1/{$plural}';
            }

            /** @return array<string, mixed> */
            protected function makeStorePayload(): array
            {
                return [
        {$storePayload}        ];
            }

            /** @return array<string, mixed> */
            protected function makeUpdatePayload(): array
            {
                return [
        {$updatePayload}        ];
            }

            protected function existingRecord(): Model
            {
                return {$ctx->name}::factory()->create();
            }

            public function test_index_paginates(): void
            {
                {$ctx->name}::factory()->count(5)->create();

                \$response = \$this->getJson("/api/v1/{$plural}?per_page=2&page=1");

                \$response->assertStatus(200);
                \$this->assertCount(2, \$response->json('data'));
                \$this->assertEquals(1, \$response->json('meta.current_page'));
            }

            public function test_index_sorts_asc(): void
            {
                {$ctx->name}::factory()->count(3)->create();

                \$response = \$this->getJson("/api/v1/{$plural}?sort=id&direction=asc");

                \$response->assertStatus(200);
                \$data = \$response->json('data');
                \$this->assertGreaterThan(1, count(\$data), 'Expected at least 2 records in response');
                \$this->assertLessThanOrEqual(\$data[1]['id'], \$data[0]['id']);
            }

            public function test_index_sorts_desc(): void
            {
                {$ctx->name}::factory()->count(3)->create();

                \$response = \$this->getJson("/api/v1/{$plural}?sort=id&direction=desc");

                \$response->assertStatus(200);
                \$data = \$response->json('data');
                \$this->assertGreaterThan(1, count(\$data), 'Expected at least 2 records in response');
                \$this->assertGreaterThanOrEqual(\$data[1]['id'], \$data[0]['id']);
            }
        {$filterTests}}
        PHP;

        $this->writeFile($files, "{$ctx->featureTestPath}/{$ctx->name}ApiTest.php", $content);
    }

    private function apiFilterTestMethod(DomainContext $ctx, string $field, string $val1, string $val2): string
    {
        $plural = $ctx->plural;

        return <<<PHP

            public function test_index_filters_by_{$field}(): void
            {
                {$ctx->name}::factory()->create(['{$field}' => '{$val1}']);
                {$ctx->name}::factory()->create(['{$field}' => '{$val2}']);

                \$response = \$this->getJson("/api/v1/{$plural}?{$field}=" . urlencode('{$val1}'));

                \$response->assertStatus(200);
                \$this->assertCount(1, \$response->json('data'));
            }

        PHP;
    }
}
