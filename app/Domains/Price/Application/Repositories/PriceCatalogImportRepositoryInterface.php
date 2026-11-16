<?php

namespace Domains\Price\Application\Repositories;

interface PriceCatalogImportRepositoryInterface
{
    public function put(int $storeId, int $productId, int $kopecks): void;
}
