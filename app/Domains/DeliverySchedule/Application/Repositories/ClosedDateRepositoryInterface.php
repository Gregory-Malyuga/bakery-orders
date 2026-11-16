<?php

namespace Domains\DeliverySchedule\Application\Repositories;

use Illuminate\Support\Collection;

interface ClosedDateRepositoryInterface
{
    /** @return Collection<int, \stdClass> */
    public function closedDates(): Collection;

    public function close(string $date, string $reason): void;

    public function open(string $date): void;

    public function isClosed(string $date): bool;

    public function reason(string $date): ?string;

    /** @return list<string> */
    public function closedDatesFrom(string $date): array;

    /** @param callable():void $callback */
    public function transaction(callable $callback): void;
}
