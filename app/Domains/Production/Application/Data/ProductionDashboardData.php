<?php

namespace Domains\Production\Application\Data;

use Shared\Http\Data\BaseData;

class ProductionDashboardData extends BaseData
{
    /** @param array<string, mixed> $view */
    public function __construct(public readonly array $view) {}
}
