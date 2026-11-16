<?php

namespace Domains\DeliverySheet\Application\Queries\Get;

use Shared\Bus\BaseQuery;

readonly class GetSheetQuery implements BaseQuery
{
    public function __construct(public int $sheetId) {}
}
