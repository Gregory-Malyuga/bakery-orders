<?php

namespace Domains\Report\Presentation\Http\Controllers;

use Domains\Report\Application\Exports\PeriodReportWorkbookInterface;
use Domains\Report\Application\Queries\Build\BuildPeriodReportHandler;
use Domains\Report\Application\Queries\Build\BuildPeriodReportQuery;
use Domains\StockPrice\Application\StockPrices;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportController
{
    public function __construct(
        private readonly BuildPeriodReportHandler $periods,
        private readonly PeriodReportWorkbookInterface $periodWorkbook,
        private readonly StockPrices $stockPrices,
    ) {}

    /** @return array{from:string,to:string} */
    private function period(Request $request): array
    {
        /** @var array{from?:string,to?:string} $input */
        $input = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);
        $today = now('Europe/Moscow');
        $from = $input['from'] ?? $today->copy()->startOfMonth()->toDateString();
        $to = $input['to'] ?? $today->toDateString();
        abort_if($from > $to, 422, 'Начало периода позже конца.');

        return ['from' => $from, 'to' => $to];
    }

    public function report(Request $request): View
    {
        $period = $this->period($request);
        $report = $this->periods->handle(new BuildPeriodReportQuery($period['from'], $period['to']))
            ->report;

        return view('bakery.period-report', [
            'from' => $period['from'],
            'to' => $period['to'],
            'report' => $report,
            'stockRows' => $this->stockPrices->dailyRows($period['from'], $period['to']),
        ]);
    }

    public function downloadReport(Request $request): BinaryFileResponse
    {
        $period = $this->period($request);
        $report = $this->periods->handle(new BuildPeriodReportQuery($period['from'], $period['to']))
            ->report;

        return response()
            ->download(
                $this->periodWorkbook->create(
                    $report,
                    $this->stockPrices->dailyRows($period['from'], $period['to']),
                ),
                'otchet-'.$period['from'].'-'.$period['to'].'.xlsx',
                [
                    'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                ],
            )
            ->deleteFileAfterSend(true);
    }
}
