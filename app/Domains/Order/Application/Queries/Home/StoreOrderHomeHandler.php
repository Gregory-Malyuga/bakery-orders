<?php

namespace Domains\Order\Application\Queries\Home;

use Domains\Order\Application\Data\StoreOrderHomeData;
use Domains\Order\Application\StorefrontReadService;
use Shared\Bus\QueryHandlerInterface;

readonly class StoreOrderHomeHandler implements QueryHandlerInterface
{
    public function __construct(private StorefrontReadService $read) {}

    public function handle(object $message): StoreOrderHomeData
    {
        assert($message instanceof StoreOrderHomeQuery);

        return new StoreOrderHomeData(...$this->read->home($message->storeId, $message->accessible));
    }
}
