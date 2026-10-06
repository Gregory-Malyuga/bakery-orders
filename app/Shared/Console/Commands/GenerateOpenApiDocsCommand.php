<?php

namespace Shared\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionUnionType;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

class GenerateOpenApiDocsCommand extends Command
{
    protected $signature = 'openapi:generate
        {--domain= : Regenerate a specific domain (e.g. Store or Users/User)}
        {--dry-run : Print generated content without writing files}';

    protected $description = 'Regenerate OpenAPI attributes for domain controllers from Spatie Data DTOs.';

    public function __construct(private readonly Filesystem $files)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $domains = $this->discoverDomains();

        if (empty($domains)) {
            $this->warn('No domains with controllers found.');

            return self::SUCCESS;
        }

        foreach ($domains as [$domainPath, $domainName]) {
            $this->generateForDomain($domainPath, $domainName);
        }

        return self::SUCCESS;
    }

    // ── Discovery ──────────────────────────────────────────────────────────────

    /** @return list<array{string, string}> */
    private function discoverDomains(): array
    {
        $domainsRoot = base_path('app/Domains');

        if ($specific = $this->option('domain')) {
            $path = $domainsRoot.'/'.str_replace('\\', '/', $specific);

            return [[$path, basename($path)]];
        }

        $result = [];
        $this->collectDomains($domainsRoot, $result);

        return $result;
    }

    /** @param list<array{string, string}> $result */
    private function collectDomains(string $path, array &$result): void
    {
        if (! $this->files->isDirectory($path)) {
            return;
        }

        if ($this->files->isDirectory($path.'/Presentation/Http/Controllers')) {
            $result[] = [$path, basename($path)];

            return;
        }

        foreach ($this->files->directories($path) as $sub) {
            /** @var string $sub */
            $this->collectDomains($sub, $result);
        }
    }

    // ── Per-domain generation ──────────────────────────────────────────────────

    private function generateForDomain(string $domainPath, string $domainName): void
    {
        $controllerPath = "{$domainPath}/Presentation/Http/Controllers/{$domainName}Controller.php";

        if (! $this->files->exists($controllerPath)) {
            $this->warn("  No controller: {$controllerPath}");

            return;
        }

        $controllerSrc = $this->files->get($controllerPath);
        $controllerNs = $this->extractNamespace($controllerSrc);
        $controllerFqcn = $controllerNs.'\\'.$domainName.'Controller';
        $baseNs = (string) preg_replace('/\\\\Presentation\\\\Http\\\\Controllers$/', '', $controllerNs);

        $dataBase = $baseNs.'\\Application\\Data\\';
        $legacyFqcn = $this->resolveImport($controllerSrc, $domainName.'Data')
            ?? ($this->safeClassExists($dataBase.$domainName.'Data') ? $dataBase.$domainName.'Data' : null);

        $resourceFqcn = $this->resolveImport($controllerSrc, $domainName.'Resource')
            ?? ($this->safeClassExists($dataBase.$domainName.'Resource') ? $dataBase.$domainName.'Resource' : $legacyFqcn);

        $createFqcn = $this->resolveImport($controllerSrc, 'Create'.$domainName.'Data')
            ?? ($this->safeClassExists($dataBase.'Create'.$domainName.'Data') ? $dataBase.'Create'.$domainName.'Data' : $legacyFqcn);

        $updateFqcn = $this->resolveImport($controllerSrc, 'Update'.$domainName.'Data')
            ?? ($this->safeClassExists($dataBase.'Update'.$domainName.'Data') ? $dataBase.'Update'.$domainName.'Data' : $legacyFqcn);

        if ($resourceFqcn === null || ! $this->safeClassExists($resourceFqcn)) {
            $this->warn("  Resource/Data class not found for {$domainName}");

            return;
        }

        $filterFqcn = $dataBase.$domainName.'FilterData';
        $resourceProps = $this->reflectConstructorParams($resourceFqcn);
        $createProps = $createFqcn && $this->safeClassExists($createFqcn) ? $this->reflectConstructorParams($createFqcn) : $resourceProps;
        $updateProps = $updateFqcn && $this->safeClassExists($updateFqcn) ? $this->reflectConstructorParams($updateFqcn) : $resourceProps;
        $filterProps = $this->safeClassExists($filterFqcn) ? $this->reflectConstructorParams($filterFqcn) : [];

        $prefix = $this->resolveRoutePrefix($controllerFqcn);
        $resource = Str::kebab(Str::plural($domainName));
        $routePrefix = $prefix !== null ? $prefix.'/'.$resource : null;

        if ($routePrefix === null) {
            $routePrefix = 'v1/'.$resource;
            $this->warn("  Routes not found for {$domainName}, using fallback: {$routePrefix}");
        }

        $openApiNs = $baseNs.'\\Presentation\\Http\\OpenApi';
        $content = $this->buildOpenApiFile($domainName, $openApiNs, $routePrefix, $resourceProps, $createProps, $updateProps, $filterProps);
        $outputPath = "{$domainPath}/Presentation/Http/OpenApi/{$domainName}OpenApi.php";

        if ($this->option('dry-run')) {
            $this->line("=== {$outputPath} ===");
            $this->line($content);

            return;
        }

        $this->files->put($outputPath, $content);
        $this->line("  <info>Generated:</info> {$outputPath}");
    }

    // ── Reflection ─────────────────────────────────────────────────────────────

    /**
     * @return list<array{name: string, phpType: string, nullable: bool, hasDefault: bool}>
     */
    private function reflectConstructorParams(string $fqcn): array
    {
        /** @var class-string $fqcn */
        $rc = new ReflectionClass($fqcn);
        $ctor = $rc->getConstructor();

        if (! $ctor) {
            return [];
        }

        $result = [];

        foreach ($ctor->getParameters() as $param) {
            $type = $param->getType();
            $phpType = 'string';
            $nullable = false;

            if ($type instanceof ReflectionNamedType) {
                $phpType = $type->getName();
                $nullable = $type->allowsNull();
            } elseif ($type instanceof ReflectionUnionType) {
                $nullable = $type->allowsNull();
                // Pick first concrete (non-null, non-Optional) type
                foreach ($type->getTypes() as $t) {
                    if ($t instanceof ReflectionNamedType && ! in_array($t->getName(), ['null', Optional::class], true)) {
                        $phpType = $t->getName();
                        break;
                    }
                }
            }

            $result[] = [
                'name' => $param->getName(),
                'phpType' => $phpType,
                'nullable' => $nullable,
                'hasDefault' => $param->isDefaultValueAvailable(),
            ];
        }

        return $result;
    }

    // ── Route parsing ──────────────────────────────────────────────────────────

    private function resolveRoutePrefix(string $controllerFqcn): ?string
    {
        return $this->findPrefixInRouteFile(base_path('routes/api.php'), $controllerFqcn);
    }

    private function findPrefixInRouteFile(string $path, string $controllerFqcn): ?string
    {
        if (! $this->files->exists($path)) {
            return null;
        }

        $content = $this->files->get($path);

        // Route files use the short class name after `use`, e.g. BodyTypeController::class
        $shortName = substr($controllerFqcn, strrpos($controllerFqcn, '\\') + 1);
        $pos = strpos($content, $shortName.'::class');

        if ($pos === false) {
            return null;
        }

        $before = substr($content, 0, $pos);

        preg_match_all("/Route::prefix\('([^']+)'\)/", $before, $m);

        if (empty($m[1])) {
            return null;
        }

        return end($m[1]);
    }

    // ── Code generation ────────────────────────────────────────────────────────

    /**
     * @param  list<array{name: string, phpType: string, nullable: bool, hasDefault: bool}>  $resourceProps
     * @param  list<array{name: string, phpType: string, nullable: bool, hasDefault: bool}>  $createProps
     * @param  list<array{name: string, phpType: string, nullable: bool, hasDefault: bool}>  $updateProps
     * @param  list<array{name: string, phpType: string, nullable: bool, hasDefault: bool}>  $filterProps
     */
    private function buildOpenApiFile(
        string $name,
        string $ns,
        string $routePrefix,
        array $resourceProps,
        array $createProps,
        array $updateProps,
        array $filterProps,
    ): string {
        $tag = Str::plural($name);
        $schemaProps = $this->renderSchemaProps($resourceProps);
        $filterParams = $this->renderFilterParams($filterProps);
        $postBodyProps = $this->renderBodyProps($createProps);
        $postBodyRequired = $this->renderRequiredList($createProps);
        $putBodyProps = $this->renderBodyProps($updateProps);
        $postBody = $this->renderRequestBody($postBodyProps, $postBodyRequired, required: true);
        $putBody = $this->renderRequestBody($putBodyProps, '', required: false);
        $security = "    security: [['bearerAuth' => []]],\n";

        $listPath = '/api/'.$routePrefix;
        $itemPath = $listPath.'/{id}';

        $out = "<?php\n\nnamespace {$ns};\n\nuse OpenApi\\Attributes as OA;\n\n"
            ."#[OA\\Tag(name: '{$tag}', description: '{$name} management')]\n"
            ."#[OA\\Schema(\n"
            ."    schema: '{$name}',\n"
            ."    properties: [\n"
            ."{$schemaProps}"
            ."    ],\n"
            .")]\n"
            ."#[OA\\Get(\n"
            ."    path: '{$listPath}',\n"
            ."    tags: ['{$tag}'],\n"
            ."    summary: 'List {$tag}',\n"
            ."{$security}"
            ."    parameters: [\n"
            ."{$filterParams}"
            ."    ],\n"
            ."    responses: [\n"
            ."        new OA\\Response(\n"
            ."            response: 200,\n"
            ."            description: 'Paginated list',\n"
            ."            content: new OA\\JsonContent(\n"
            ."                properties: [\n"
            ."                    new OA\\Property(property: 'data', type: 'array', items: new OA\\Items(ref: '#/components/schemas/{$name}')),\n"
            ."                    new OA\\Property(property: 'meta', type: 'object'),\n"
            ."                    new OA\\Property(property: 'links', type: 'object'),\n"
            ."                ]\n"
            ."            )\n"
            ."        ),\n"
            ."        new OA\\Response(response: 401, description: 'Unauthenticated'),\n"
            ."    ],\n"
            .")]\n"
            ."#[OA\\Get(\n"
            ."    path: '{$itemPath}',\n"
            ."    tags: ['{$tag}'],\n"
            ."    summary: 'Get {$name} by ID',\n"
            ."{$security}"
            ."    parameters: [\n"
            ."        new OA\\Parameter(name: 'id', in: 'path', required: true, schema: new OA\\Schema(type: 'integer')),\n"
            ."    ],\n"
            ."    responses: [\n"
            ."        new OA\\Response(response: 200, description: 'Success', content: new OA\\JsonContent(ref: '#/components/schemas/{$name}')),\n"
            ."        new OA\\Response(response: 401, description: 'Unauthenticated'),\n"
            ."        new OA\\Response(response: 404, description: 'Not found'),\n"
            ."    ],\n"
            .")]\n"
            ."#[OA\\Post(\n"
            ."    path: '{$listPath}',\n"
            ."    tags: ['{$tag}'],\n"
            ."    summary: 'Create {$name}',\n"
            ."{$security}"
            ."{$postBody}"
            ."    responses: [\n"
            ."        new OA\\Response(response: 201, description: 'Created', content: new OA\\JsonContent(ref: '#/components/schemas/{$name}')),\n"
            ."        new OA\\Response(response: 401, description: 'Unauthenticated'),\n"
            ."        new OA\\Response(response: 403, description: 'Forbidden'),\n"
            ."        new OA\\Response(response: 422, description: 'Validation error'),\n"
            ."    ],\n"
            .")]\n"
            ."#[OA\\Put(\n"
            ."    path: '{$itemPath}',\n"
            ."    tags: ['{$tag}'],\n"
            ."    summary: 'Update {$name}',\n"
            ."{$security}"
            ."    parameters: [\n"
            ."        new OA\\Parameter(name: 'id', in: 'path', required: true, schema: new OA\\Schema(type: 'integer')),\n"
            ."    ],\n"
            ."{$putBody}"
            ."    responses: [\n"
            ."        new OA\\Response(response: 200, description: 'Updated', content: new OA\\JsonContent(ref: '#/components/schemas/{$name}')),\n"
            ."        new OA\\Response(response: 401, description: 'Unauthenticated'),\n"
            ."        new OA\\Response(response: 403, description: 'Forbidden'),\n"
            ."        new OA\\Response(response: 404, description: 'Not found'),\n"
            ."        new OA\\Response(response: 422, description: 'Validation error'),\n"
            ."    ],\n"
            .")]\n"
            ."#[OA\\Delete(\n"
            ."    path: '{$itemPath}',\n"
            ."    tags: ['{$tag}'],\n"
            ."    summary: 'Delete {$name}',\n"
            ."{$security}"
            ."    parameters: [\n"
            ."        new OA\\Parameter(name: 'id', in: 'path', required: true, schema: new OA\\Schema(type: 'integer')),\n"
            ."    ],\n"
            ."    responses: [\n"
            ."        new OA\\Response(response: 204, description: 'Deleted'),\n"
            ."        new OA\\Response(response: 401, description: 'Unauthenticated'),\n"
            ."        new OA\\Response(response: 403, description: 'Forbidden'),\n"
            ."        new OA\\Response(response: 404, description: 'Not found'),\n"
            ."    ],\n"
            .")]\n";

        $out .= "class {$name}OpenApi {}\n";

        return $out;
    }

    /**
     * @param  list<array{name: string, phpType: string, nullable: bool, hasDefault: bool}>  $props
     */
    private function renderSchemaProps(array $props): string
    {
        $lines = '';

        foreach ($props as $p) {
            if ($this->isDataClass($p['phpType'])) {
                $lines .= $this->renderNestedObjectProperty($p['name'], $p['phpType'], $p['nullable']);

                continue;
            }

            $oaType = $this->phpTypeToOa($p['phpType']);
            $extras = $this->oaExtras($p['name'], $p['phpType'], $p['nullable']);
            $items = $oaType === 'array' ? ", items: new OA\\Items(type: 'string')" : '';
            $lines .= "        new OA\\Property(property: '{$p['name']}', type: '{$oaType}'{$extras}{$items}),\n";
        }

        return $lines;
    }

    /**
     * @param  list<array{name: string, phpType: string, nullable: bool, hasDefault: bool}>  $props
     */
    private function renderBodyProps(array $props): string
    {
        $skip = ['id', 'created_at', 'updated_at'];
        $lines = '';

        foreach ($props as $p) {
            if (in_array($p['name'], $skip, true)) {
                continue;
            }

            // Skip read-only denormalized display fields (e.g. fuel_type_name alongside fuel_type_id)
            if ($p['nullable'] && $p['hasDefault'] && str_ends_with($p['name'], '_name')) {
                continue;
            }

            // Skip nested Data objects — resolved server-side, not writable via API
            if ($this->isDataClass($p['phpType'])) {
                continue;
            }

            $oaType = $this->phpTypeToOa($p['phpType']);
            $extras = $this->oaExtras($p['name'], $p['phpType'], $p['nullable']);
            $items = $oaType === 'array' ? ", items: new OA\\Items(type: 'string')" : '';
            $lines .= "                new OA\\Property(property: '{$p['name']}', type: '{$oaType}'{$extras}{$items}),\n";
        }

        return $lines;
    }

    /**
     * @param  list<array{name: string, phpType: string, nullable: bool, hasDefault: bool}>  $props
     */
    private function renderRequiredList(array $props): string
    {
        $skip = ['id', 'created_at', 'updated_at'];
        $required = [];

        foreach ($props as $p) {
            if (in_array($p['name'], $skip, true)) {
                continue;
            }

            if ($p['nullable'] && $p['hasDefault'] && str_ends_with($p['name'], '_name')) {
                continue;
            }

            if ($this->isDataClass($p['phpType'])) {
                continue;
            }

            if (! $p['hasDefault'] && ! $p['nullable']) {
                $required[] = "'{$p['name']}'";
            }
        }

        return implode(', ', $required);
    }

    private function isDataClass(string $phpType): bool
    {
        return $this->safeClassExists($phpType) && is_subclass_of($phpType, Data::class);
    }

    private function renderNestedObjectProperty(string $name, string $phpType, bool $nullable): string
    {
        $nestedProps = $this->reflectConstructorParams($phpType);
        $nullableStr = $nullable ? ', nullable: true' : '';
        $subProps = implode(', ', array_map(
            fn ($np) => "new OA\\Property(property: '{$np['name']}', type: '{$this->phpTypeToOa($np['phpType'])}')",
            $nestedProps
        ));

        return "        new OA\\Property(property: '{$name}'{$nullableStr}, properties: [{$subProps}], type: 'object'),\n";
    }

    /**
     * @param  list<array{name: string, phpType: string, nullable: bool, hasDefault: bool}>  $props
     */
    private function renderFilterParams(array $props): string
    {
        $lines = '';

        foreach ($props as $p) {
            $oaType = $this->phpTypeToOa($p['phpType']);
            $schema = $oaType === 'array'
                ? "new OA\\Schema(type: 'array', items: new OA\\Items(type: 'string'))"
                : "new OA\\Schema(type: '{$oaType}')";
            $lines .= "        new OA\\Parameter(name: '{$p['name']}', in: 'query', required: false, schema: {$schema}),\n";
        }

        $lines .= "        new OA\\Parameter(name: 'page', in: 'query', required: false, schema: new OA\\Schema(type: 'integer', default: 1)),\n";
        $lines .= "        new OA\\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\\Schema(type: 'integer', default: 15)),\n";

        return $lines;
    }

    private function renderRequestBody(string $bodyProps, string $requiredList, bool $required): string
    {
        if ($bodyProps === '') {
            return '';
        }

        $req = $required ? 'true' : 'false';
        $requiredLine = $requiredList !== ''
            ? "            required: [{$requiredList}],\n"
            : '';

        return "    requestBody: new OA\\RequestBody(\n"
            ."        required: {$req},\n"
            ."        content: new OA\\JsonContent(\n"
            .$requiredLine
            ."            properties: [\n"
            .$bodyProps
            ."            ],\n"
            ."        ),\n"
            ."    ),\n";
    }

    // ── Type helpers ───────────────────────────────────────────────────────────

    private function phpTypeToOa(string $phpType): string
    {
        return match ($phpType) {
            'int' => 'integer',
            'float' => 'number',
            'bool' => 'boolean',
            'array' => 'array',
            default => 'string',
        };
    }

    private function oaExtras(string $fieldName, string $phpType, bool $nullable): string
    {
        $extras = '';

        if ($nullable) {
            $extras .= ', nullable: true';
        }

        $format = match (true) {
            str_ends_with($fieldName, 'email') => 'email',
            in_array($fieldName, ['created_at', 'updated_at'], true), str_ends_with($fieldName, '_at') => 'date-time',
            str_ends_with($fieldName, '_date') => 'date',
            $phpType === 'float' => 'float',
            default => '',
        };

        if ($format !== '') {
            $extras .= ", format: '{$format}'";
        }

        return $extras;
    }

    private function safeClassExists(string $fqcn): bool
    {
        try {
            return class_exists($fqcn);
        } catch (\Throwable) {
            return false;
        }
    }

    // ── PHP parsing helpers ────────────────────────────────────────────────────

    private function extractNamespace(string $src): string
    {
        if (preg_match('/^namespace\s+([^;]+);/m', $src, $m)) {
            return trim($m[1]);
        }

        return '';
    }

    private function resolveImport(string $src, string $shortName): ?string
    {
        if (preg_match('/^use\s+([\w\\\\]+\\\\'.preg_quote($shortName, '/').');/m', $src, $m)) {
            return $m[1];
        }

        return null;
    }
}
