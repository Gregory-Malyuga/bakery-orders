<?php

namespace Domains\Price\Application\Repositories;

interface PriceReferenceImportRepositoryInterface
{
    public function setOrDelete(int $storeId, int $productId, ?int $kopecks): void;
}
