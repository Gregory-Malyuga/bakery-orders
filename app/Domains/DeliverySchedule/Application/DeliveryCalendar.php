<?php

namespace Domains\DeliverySchedule\Application;

use Domains\DeliverySchedule\Application\Repositories\ClosedDateRepositoryInterface;
use Domains\Order\Application\OrderCutoff;

class DeliveryCalendar
{
    public function __construct(
        private readonly OrderCutoff $cutoff,
        private readonly ClosedDateRepositoryInterface $dates,
    ) {}

    public function isClosed(string $deliveryDate): bool
    {
        return $this->dates->isClosed($deliveryDate);
    }

    public function reason(string $deliveryDate): ?string
    {
        $value = $this->dates->reason($deliveryDate);

        return is_string($value) ? $value : null;
    }

    public function isOrderOpen(): bool
    {
        return $this->cutoff->isOpen() && ! $this->isClosed($this->tomorrow());
    }

    public function tomorrow(): string
    {
        return now('Europe/Moscow')->addDay()->toDateString();
    }

    /** @return array{order_date:string,delivery_date:string} */
    public function nextWindow(): array
    {
        $today = now('Europe/Moscow')->toImmutable()->startOfDay();
        $delivery = $today->addDays($this->cutoff->isOpen() ? 1 : 2);
        $closed = [];
        foreach ($this->dates->closedDatesFrom($delivery->toDateString()) as $date) {
            $closed[$date] = true;
        }
        while (isset($closed[$delivery->toDateString()])) {
            $delivery = $delivery->addDay();
        }

        return [
            'order_date' => $delivery->subDay()->toDateString(),
            'delivery_date' => $delivery->toDateString(),
        ];
    }
}
