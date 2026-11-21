<?php

namespace Domains\Report\Providers;

use Domains\Report\Application\Exports\PeriodReportWorkbookInterface;
use Domains\Report\Application\Repositories\PeriodOrderRepositoryInterface;
use Domains\Report\Infrastructure\Exports\PeriodReportWorkbook;
use Domains\Report\Infrastructure\Repositories\PeriodOrderRepository;
use Illuminate\Support\ServiceProvider;

class ReportServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PeriodOrderRepositoryInterface::class, PeriodOrderRepository::class);
        $this->app->bind(PeriodReportWorkbookInterface::class, PeriodReportWorkbook::class);
    }
}
