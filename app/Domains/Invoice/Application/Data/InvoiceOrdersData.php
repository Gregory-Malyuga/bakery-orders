<?php

namespace Domains\Invoice\Application\Data;

use Shared\Http\Data\BaseData;

class InvoiceOrdersData extends BaseData
{
    /** @param array<string, mixed> $view */
    public function __construct(public readonly array $view) {}
}
