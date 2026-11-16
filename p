<?php

namespace Domains\Bakery\Application;

use Domains\Report\Application\Repositories\PeriodOrderRepositoryInterface;

/** @deprecated Use Domains\Report\Application\PeriodReportService. */
class PeriodReportService extends \Domains\Report\Application\PeriodReportService
{
    public function __construct(
        ProductionCalculator $production,
        OrderReturnSummary $returnSummary,
        ?PeriodOrderRepositoryInterface $orders = null,
    ) {
        parent::__construct($production, $returnSummary, $orders ?? app(PeriodOrderRepositoryInterface::class));
    }
}
