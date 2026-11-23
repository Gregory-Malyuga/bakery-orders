<?php

namespace Domains\Order\Application\Data;

use Domains\Order\Domain\Models\Order;

readonly class StoreOrderShowData
{
    public function __construct(public Order $order, public bool $canCancel, public bool $canRepeat) {}

    /** @return array{order:Order,canCancel:bool,canRepeat:bool} */
    public function viewData(): array
    {
        return ['order' => $this->order, 'canCancel' => $this->canCancel, 'canRepeat' => $this->canRepeat];
    }
}
