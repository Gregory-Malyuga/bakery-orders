<?php

namespace Domains\Order\Application\Queries\Repeat;

use Shared\Bus\BaseQuery;

readonly class StoreOrderRepeatQuery implements BaseQuery
{
    /** @param list<int> $accessible */
    public function __construct(public int $orderId, public array $accessible) {}
}
