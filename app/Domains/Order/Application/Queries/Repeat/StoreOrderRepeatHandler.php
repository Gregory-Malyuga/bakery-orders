<?php

namespace Domains\Order\Application\Queries\Repeat;

use Domains\Order\Application\Data\StoreOrderViewData;
use Domains\Order\Application\StorefrontReadService;
use Shared\Bus\QueryHandlerInterface;

readonly class StoreOrderRepeatHandler implements QueryHandlerInterface
{
    public function __construct(private StorefrontReadService $read) {}

    public function handle(object $message): StoreOrderViewData
    {
        assert($message instanceof StoreOrderRepeatQuery);

        return new StoreOrderViewData($this->read->repeat($message->orderId, $message->accessible));
    }
}
