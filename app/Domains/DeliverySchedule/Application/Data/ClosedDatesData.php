<?php

namespace Domains\DeliverySchedule\Application\Data;

use Illuminate\Support\Collection;
use Shared\Http\Data\BaseData;

class ClosedDatesData extends BaseData
{
    /**
     * @param  Collection<int, \stdClass>  $dates
     * @param  Collection<int|string, int>  $orderCounts
     */
    public function __construct(
        public readonly Collection $dates,
        public readonly Collection $orderCounts,
    ) {}
}
