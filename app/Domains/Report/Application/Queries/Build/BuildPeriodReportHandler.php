<?php

namespace Domains\Report\Application\Queries\Build;

use Domains\Report\Application\Data\PeriodReportData;
use Domains\Report\Application\PeriodReportService;
use Shared\Bus\QueryHandlerInterface;

readonly class BuildPeriodReportHandler implements QueryHandlerInterface
{
    public function __construct(private PeriodReportService $service) {}

    public function handle(object $message): PeriodReportData
    {
        assert($message instanceof BuildPeriodReportQuery);

        return new PeriodReportData($this->service->build($message->from, $message->to));
    }
}
