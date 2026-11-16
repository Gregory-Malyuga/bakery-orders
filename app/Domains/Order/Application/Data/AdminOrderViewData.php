<?php

namespace Domains\Order\Application\Data;

use Shared\Http\Data\BaseData;

class AdminOrderViewData extends BaseData
{
    /** @param array<string, mixed> $view */
    public function __construct(public readonly array $view) {}
}
