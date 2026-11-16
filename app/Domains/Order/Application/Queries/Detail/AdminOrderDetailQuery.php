<?php

namespace Domains\Order\Application\Queries\Detail;

use Shared\Bus\BaseQuery;

readonly class AdminOrderDetailQuery implements BaseQuery
{
    public function __construct(public int $orderId) {}
}
