<?php

namespace Domains\Production\Application\Queries\Dashboard;

use Shared\Bus\BaseQuery;

readonly class ProductionDashboardQuery implements BaseQuery
{
    public function __construct(public string $date, public string $tab, public int $page) {}
}
