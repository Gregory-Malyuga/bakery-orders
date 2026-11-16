<?php

namespace Domains\DeliverySheet\Application\Queries\Edit;

use Shared\Bus\BaseQuery;

readonly class EditSheetQuery implements BaseQuery
{
    public function __construct(public int $sheetId) {}
}
