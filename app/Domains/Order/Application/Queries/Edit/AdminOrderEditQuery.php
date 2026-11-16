<?php

namespace Domains\Order\Application\Queries\Edit;

use Shared\Bus\BaseQuery;

readonly class AdminOrderEditQuery implements BaseQuery
{
    public function __construct(public int $orderId) {}
}
