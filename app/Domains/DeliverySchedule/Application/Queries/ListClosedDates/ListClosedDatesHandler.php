<?php

namespace Domains\DeliverySchedule\Application\Queries\ListClosedDates;

use Domains\DeliverySchedule\Application\Data\ClosedDatesData;
use Domains\DeliverySchedule\Application\Repositories\ClosedDateRepositoryInterface;
use Domains\Order\Application\Repositories\OrderCalendarRepositoryInterface;
use Shared\Bus\QueryHandlerInterface;

readonly class ListClosedDatesHandler implements QueryHandlerInterface
{
    public function __construct(
        private ClosedDateRepositoryInterface $repository,
        private OrderCalendarRepositoryInterface $orders,
    ) {}

    public function handle(object $message): ClosedDatesData
    {
        assert($message instanceof ListClosedDatesQuery);

        return new ClosedDatesData(
            $this->repository->closedDates(),
            $this->orders->acceptedCountsByDate(),
        );
    }
}
