<?php

namespace Domains\Price\Application\Commands\ReversePrice;

use Domains\Price\Application\Repositories\PriceOperationsRepositoryInterface;
use Shared\Bus\CommandHandlerInterface;

readonly class ReversePriceHandler implements CommandHandlerInterface
{
    public function __construct(private PriceOperationsRepositoryInterface $repository) {}

    public function handle(object $message): ?int
    {
        assert($message instanceof ReversePriceCommand);

        abort_unless($this->repository->belongsToProduct($message->operationId, $message->productId), 404);
        $this->repository->reverse($message->operationId, $message->reason, $message->actorId);

        return null;
    }
}
