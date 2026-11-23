<?php

namespace Domains\Order\Application\Queries\Repeat;

use Domains\Order\Application\Data\StoreOrderHomeData;
use Domains\Order\Application\StorefrontReadService;
use Shared\Bus\QueryHandlerInterface;

readonly class StoreOrderRepeatHandler implements QueryHandlerInterface
{
    public function __construct(private StorefrontReadService $read) {}

    public function handle(object $message): StoreOrderHomeData
    {
        assert($message instanceof StoreOrderRepeatQuery);

        return new StoreOrderHomeData(
            ...$this->read->repeat($message->orderId, $message->accessible),
        );
    }
}
