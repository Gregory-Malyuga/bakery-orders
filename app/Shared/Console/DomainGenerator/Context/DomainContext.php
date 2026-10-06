<?php

namespace Shared\Console\DomainGenerator\Context;

readonly class DomainContext
{
    /**
     * @param  array<string, array{phpType: string, nullable: bool, migration: string, decimal: bool, faker: string, rules: list<string>}>  $fields
     */
    public function __construct(
        public string $name,
        public string $ns,
        public string $table,
        public string $plural,
        public array $fields,
        public string $basePath,
        public string $unitTestPath,
        public string $featureTestPath,
        public bool $withElasticsearch,
        public bool $withCacheWarmer,
    ) {}
}
