<?php

namespace Domains\Order\Application\Queries\Home;

use Domains\Order\Application\Data\StoreOrderViewData;
use Domains\Order\Application\StorefrontReadService;
use Shared\Bus\QueryHandlerInterface;

readonly class StoreOrderHomeHandler implements QueryHandlerInterface
{
    public function __construct(private StorefrontReadService $read) {}

    public function handle(object $message): StoreOrderViewData
    {
        assert($message instanceof StoreOrderHomeQuery);

        return new StoreOrderViewData($this->read->home($message->storeId, $message->accessible));
    }
}
