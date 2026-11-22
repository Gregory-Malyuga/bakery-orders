<?php

namespace Domains\DeliverySheet\Application\Data;

readonly class PrintedSheetHistoryItem
{
    public function __construct(public int $id, public string $label, public string $summary) {}
}
