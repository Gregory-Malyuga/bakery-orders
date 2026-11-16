<?php

namespace Domains\Report\Application\Queries\Build;

use Shared\Bus\BaseQuery;

readonly class BuildPeriodReportQuery implements BaseQuery
{
    public function __construct(public string $from, public string $to) {}
}
