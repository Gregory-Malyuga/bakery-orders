<?php

namespace Domains\DeliverySheet\Application\Queries\Printed;

use Shared\Bus\BaseQuery;

readonly class PrintedSheetQuery implements BaseQuery
{
    public function __construct(public int $printId) {}
}
