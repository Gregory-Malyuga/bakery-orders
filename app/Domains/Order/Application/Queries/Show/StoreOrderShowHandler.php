<?php

namespace Domains\Order\Application\Queries\Show;

use Domains\Order\Application\Data\StoreOrderViewData;
use Domains\Order\Application\StorefrontReadService;
use Shared\Bus\QueryHandlerInterface;

readonly class StoreOrderShowHandler implements QueryHandlerInterface
{
    public function __construct(private StorefrontReadService $read) {}

    public function handle(object $message): StoreOrderViewData
    {
        assert($message instanceof StoreOrderShowQuery);

        return new StoreOrderViewData($this->read->show($message->orderId, $message->accessible));
    }
}
