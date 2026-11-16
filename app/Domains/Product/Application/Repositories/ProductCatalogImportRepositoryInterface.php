<?php

namespace Domains\Product\Application\Repositories;

interface ProductCatalogImportRepositoryInterface
{
    public function firstOrCreate(string $name): int;
}
