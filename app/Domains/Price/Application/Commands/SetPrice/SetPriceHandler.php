<?php

namespace Domains\Price\Application\Commands\SetPrice;

use Domains\Price\Application\Repositories\PriceOperationsRepositoryInterface;
use Shared\Bus\CommandHandlerInterface;

readonly class SetPriceHandler implements CommandHandlerInterface
{
    public function __construct(private PriceOperationsRepositoryInterface $repository) {}

    public function handle(object $message): ?int
    {
        assert($message instanceof SetPriceCommand);

        $this->repository->single($message->productId, $message->storeId, $message->kopecks, $message->actorId);

        return null;
    }
}
