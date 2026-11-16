<?php

namespace Domains\DeliverySheet\Application\Data;

use Shared\Http\Data\BaseData;

class DeliverySheetViewData extends BaseData
{
    /** @param array<string, mixed> $data */
    public function __construct(public readonly array $data) {}
}
