<?php

namespace Domains\Catalog\Application\Commands\Import;

use Shared\Bus\BaseCommand;

readonly class ImportCatalogCommand implements BaseCommand
{
    /**
     * @param  array<string, array{string,string}>  $stores
     * @param  array<string, string>  $products
     * @param  list<array{string,string,int}>  $rows
     */
    public function __construct(
        public array $stores,
        public array $products,
        public array $rows,
    ) {}
}
