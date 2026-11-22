<?php

namespace Domains\DeliverySheet\Application\Data;

readonly class DeliverySheetPrintRow
{
    public function __construct(
        public string $name,
        public string $address,
        public bool $hasOrder,
        public string $order,
        public string $returns,
        public string $due,
    ) {}
}
