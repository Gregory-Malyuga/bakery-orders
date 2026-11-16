<?php

namespace Domains\StockItem\Application\Data;

use Shared\Http\Data\BaseData;

class StockDashboardData extends BaseData
{
    /** @param array<string, mixed> $data */
    public function __construct(public readonly array $data) {}
}
