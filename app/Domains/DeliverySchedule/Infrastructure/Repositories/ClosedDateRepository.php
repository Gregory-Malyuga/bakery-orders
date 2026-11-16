<?php

namespace Domains\DeliverySchedule\Infrastructure\Repositories;

use Domains\DeliverySchedule\Application\Repositories\ClosedDateRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class ClosedDateRepository implements ClosedDateRepositoryInterface
{
    /** @return Collection<int, \stdClass> */
    public function closedDates(): Collection
    {
        return DB::table('bakery_closed_delivery_dates')->orderBy('delivery_date')->get();
    }

    public function close(string $date, string $reason): void
    {
        DB::table('bakery_closed_delivery_dates')->updateOrInsert(
            ['delivery_date' => $date],
            ['reason' => $reason, 'created_at' => now(), 'updated_at' => now()],
        );
    }

    public function open(string $date): void
    {
        DB::table('bakery_closed_delivery_dates')->where('delivery_date', $date)->delete();
    }

    public function isClosed(string $date): bool
    {
        return DB::table('bakery_closed_delivery_dates')->where('delivery_date', $date)->exists();
    }

    public function reason(string $date): ?string
    {
        $reason = DB::table('bakery_closed_delivery_dates')->where('delivery_date', $date)->value('reason');

        return is_string($reason) ? $reason : null;
    }

    /** @return list<string> */
    public function closedDatesFrom(string $date): array
    {
        $dates = [];
        foreach (DB::table('bakery_closed_delivery_dates')->where('delivery_date', '>=', $date)->pluck('delivery_date') as $value) {
            if (is_string($value)) {
                $dates[] = $value;
            }
        }

        return $dates;
    }

    public function transaction(callable $callback): void
    {
        DB::transaction(static function () use ($callback): void {
            $callback();
        });
    }
}
