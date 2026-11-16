<?php

namespace Domains\Order\Application\Queries\Home;

use Shared\Bus\BaseQuery;

readonly class StoreOrderHomeQuery implements BaseQuery
{
    /** @param list<int> $accessible */
    public function __construct(public int $storeId, public array $accessible) {}
}
