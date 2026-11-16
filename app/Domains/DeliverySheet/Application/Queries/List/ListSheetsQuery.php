<?php

namespace Domains\DeliverySheet\Application\Queries\List;

use Shared\Bus\BaseQuery;

readonly class ListSheetsQuery implements BaseQuery
{
    public function __construct(public ?string $date, public ?int $copyId) {}
}
