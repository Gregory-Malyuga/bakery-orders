<?php

namespace Domains\Invoice\Application\Queries\Single;

use Shared\Bus\BaseQuery;

readonly class SingleInvoiceQuery implements BaseQuery
{
    public function __construct(public int $orderId) {}
}
