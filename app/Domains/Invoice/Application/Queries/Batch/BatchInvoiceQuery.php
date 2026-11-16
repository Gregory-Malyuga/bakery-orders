<?php

namespace Domains\Invoice\Application\Queries\Batch;

use Shared\Bus\BaseQuery;

readonly class BatchInvoiceQuery implements BaseQuery
{
    public function __construct(public string $date) {}
}
