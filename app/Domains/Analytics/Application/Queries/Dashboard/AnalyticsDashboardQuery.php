<?php

namespace Domains\Analytics\Application\Queries\Dashboard;

use Shared\Bus\BaseQuery;

readonly class AnalyticsDashboardQuery implements BaseQuery
{
    public function __construct(
        public string $from,
        public string $to,
        public ?int $storeId,
        public ?int $productId,
    ) {}
}
