<?php

namespace Domains\Catalog\Application\Queries\Catalog;

use Shared\Bus\BaseQuery;

readonly class GetCatalogQuery implements BaseQuery
{
    public function __construct(
        public ?int $storeId,
        public string $tab,
        public int $page,
        public string $search,
    ) {}
}
