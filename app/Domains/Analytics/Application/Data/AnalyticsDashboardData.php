<?php

namespace Domains\Analytics\Application\Data;

use Shared\Http\Data\BaseData;

class AnalyticsDashboardData extends BaseData
{
    /** @param array<string, mixed> $view */
    public function __construct(public readonly array $view) {}
}
