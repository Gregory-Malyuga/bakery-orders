<?php

namespace Domains\DeliverySheet\Application\Data;

readonly class SavedSheetOption
{
    public function __construct(public int $id, public string $label) {}
}
