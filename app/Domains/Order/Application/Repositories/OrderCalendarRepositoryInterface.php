<?php

namespace Domains\Order\Application\Repositories;

use Illuminate\Support\Collection;

interface OrderCalendarRepositoryInterface
{
    public function acceptedCountOn(string $deliveryDate): int;

    /** @return Collection<int|string, int> */
    public function acceptedCountsByDate(): Collection;
}
