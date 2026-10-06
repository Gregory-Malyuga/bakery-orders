<?php

namespace Shared\Console\DomainGenerator\Generators\Presentation;

use Illuminate\Filesystem\Filesystem;
use Shared\Console\DomainGenerator\Context\DomainContext;
use Shared\Console\DomainGenerator\Contracts\AbstractGenerator;

class OpenApiGenerator extends AbstractGenerator
{
    public function generate(DomainContext $ctx, Filesystem $files): void
    {
        $schemaProps = "        new OA\\Property(property: 'id', type: 'integer'),\n";
        foreach ($ctx->fields as $fieldName => $def) {
            $oaType = match ($def['phpType']) {
                'int' => 'integer',
                'float' => 'number',
                'bool' => 'boolean',
                'array' => 'array',
                default => 'string',
            };
            $schemaProps .= "        new OA\\Property(property: '{$fieldName}', type: '{$oaType}'),\n";
        }
        $schemaProps .= "        new OA\\Property(property: 'created_at', type: 'string', format: 'date-time'),\n";
        $schemaProps .= "        new OA\\Property(property: 'updated_at', type: 'string', format: 'date-time'),\n";

        $plural = $ctx->plural;
        $name = $ctx->name;

        $content = "<?php\n\nnamespace {$ctx->ns}\\Presentation\\Http\\OpenApi;\n\nuse OpenApi\\Attributes as OA;\n\n"
            ."#[OA\\Tag(name: '{$name}s', description: '{$name} management')]\n"
            ."#[OA\\Schema(\n"
            ."    schema: '{$name}',\n"
            ."    properties: [\n"
            ."{$schemaProps}"
            ."    ],\n"
            .")]\n"
            ."#[OA\\Get(\n"
            ."    path: '/api/v1/{$plural}',\n"
            ."    tags: ['{$name}s'],\n"
            ."    summary: 'List {$name}s',\n"
            ."    responses: [new OA\\Response(response: 200, description: 'Paginated list')],\n"
            .")]\n"
            ."#[OA\\Post(\n"
            ."    path: '/api/v1/{$plural}',\n"
            ."    tags: ['{$name}s'],\n"
            ."    summary: 'Create {$name}',\n"
            ."    responses: [new OA\\Response(response: 201, description: 'Created')],\n"
            .")]\n"
            ."#[OA\\Get(\n"
            ."    path: '/api/v1/{$plural}/{id}',\n"
            ."    tags: ['{$name}s'],\n"
            ."    summary: 'Get {$name} by ID',\n"
            ."    parameters: [new OA\\Parameter(name: 'id', in: 'path', required: true, schema: new OA\\Schema(type: 'integer'))],\n"
            ."    responses: [\n"
            ."        new OA\\Response(response: 200, description: 'Success'),\n"
            ."        new OA\\Response(response: 404, description: 'Not found'),\n"
            ."    ],\n"
            .")]\n"
            ."#[OA\\Put(\n"
            ."    path: '/api/v1/{$plural}/{id}',\n"
            ."    tags: ['{$name}s'],\n"
            ."    summary: 'Update {$name}',\n"
            ."    parameters: [new OA\\Parameter(name: 'id', in: 'path', required: true, schema: new OA\\Schema(type: 'integer'))],\n"
            ."    responses: [\n"
            ."        new OA\\Response(response: 200, description: 'Updated'),\n"
            ."        new OA\\Response(response: 404, description: 'Not found'),\n"
            ."    ],\n"
            .")]\n"
            ."#[OA\\Delete(\n"
            ."    path: '/api/v1/{$plural}/{id}',\n"
            ."    tags: ['{$name}s'],\n"
            ."    summary: 'Delete {$name}',\n"
            ."    parameters: [new OA\\Parameter(name: 'id', in: 'path', required: true, schema: new OA\\Schema(type: 'integer'))],\n"
            ."    responses: [new OA\\Response(response: 204, description: 'Deleted')],\n"
            .")]\n"
            ."class {$name}OpenApi {}\n";

        $this->writeFile($files, "{$ctx->basePath}/Presentation/Http/OpenApi/{$name}OpenApi.php", $content);
    }
}
