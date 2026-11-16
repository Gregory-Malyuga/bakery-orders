<?php

namespace Domains\Price\Application\Commands\BulkPrice;

use Domains\Price\Application\Repositories\PriceOperationsRepositoryInterface;
use Illuminate\Validation\ValidationException;
use Shared\Bus\CommandHandlerInterface;

readonly class BulkPriceHandler implements CommandHandlerInterface
{
    public function __construct(private PriceOperationsRepositoryInterface $repository) {}

    public function handle(object $message): ?int
    {
        assert($message instanceof BulkPriceCommand);

        if ($message->deltaKopecks <= 0) {
            throw ValidationException::withMessages(['rubles' => 'Изменение должно быть больше нуля.']);
        }

        $this->repository->apply(
            $message->productId,
            $message->storeIds,
            $message->deltaKopecks,
            $message->direction,
            $message->requestKey,
            $message->actorId,
        );

        return null;
    }
}
