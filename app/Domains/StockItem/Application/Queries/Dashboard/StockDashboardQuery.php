<?php

namespace Domains\StockItem\Application\Queries\Dashboard;

use Shared\Bus\BaseQuery;

readonly class StockDashboardQuery implements BaseQuery
{
    public function __construct(public string $date) {}
}
