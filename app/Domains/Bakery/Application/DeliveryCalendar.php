<?php

namespace Domains\Bakery\Application;

use Illuminate\Support\Facades\DB;

class DeliveryCalendar
{
    public function __construct(private readonly OrderCutoff $cutoff) {}

    public function isClosed(string $deliveryDate): bool
    {
        return DB::table('bakery_closed_delivery_dates')->where('delivery_date', $deliveryDate)->exists();
    }

    public function reason(string $deliveryDate): ?string
    {
        $value = DB::table('bakery_closed_delivery_dates')->where('delivery_date', $deliveryDate)->value('reason');

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
        foreach (DB::table('bakery_closed_delivery_dates')->where('delivery_date', '>=', $delivery->toDateString())->pluck('delivery_date') as $date) {
            if (is_string($date)) {
                $closed[$date] = true;
            }
        }
        while (isset($closed[$delivery->toDateString()])) {
            $delivery = $delivery->addDay();
        }

        return ['order_date' => $delivery->subDay()->toDateString(), 'delivery_date' => $delivery->toDateString()];
    }
}
