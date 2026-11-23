<?php

namespace Domains\Catalog\Application\Commands\SeedReference;

use Shared\Bus\BaseCommand;

readonly class SeedReferenceCommand implements BaseCommand
{
    /**
     * @param array{
     *   stores:list<string>,
     *   products:list<string>,
     *   prices:array<int,array<int,int|null>>
     * } $data
     */
    public function __construct(public array $data, public string $initialPassword) {}
}
