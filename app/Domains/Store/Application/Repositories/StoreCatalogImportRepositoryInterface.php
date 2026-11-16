<?php

namespace Domains\Store\Application\Repositories;

interface StoreCatalogImportRepositoryInterface
{
    public function firstOrCreate(string $name, string $address): int;
}
