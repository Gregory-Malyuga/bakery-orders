<?php

namespace Domains\Order\Application\Queries\Show;

use Shared\Bus\BaseQuery;

readonly class StoreOrderShowQuery implements BaseQuery
{
    /** @param list<int> $accessible */
    public function __construct(public int $orderId, public array $accessible) {}
}
