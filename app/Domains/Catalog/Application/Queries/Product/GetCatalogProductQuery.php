<?php

namespace Domains\Catalog\Application\Queries\Product;

use Shared\Bus\BaseQuery;

readonly class GetCatalogProductQuery implements BaseQuery
{
    public function __construct(public int $productId) {}
}
