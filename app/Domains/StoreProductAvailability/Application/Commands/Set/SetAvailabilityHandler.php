<?php

namespace Domains\StoreProductAvailability\Application\Commands\Set;

use Domains\Price\Application\Repositories\PriceAvailabilityReadRepositoryInterface;
use Domains\StoreProductAvailability\Application\Repositories\StoreProductAvailabilityRepositoryInterface;
use Shared\Bus\CommandHandlerInterface;

readonly class SetAvailabilityHandler implements CommandHandlerInterface
{
    public function __construct(
        private StoreProductAvailabilityRepositoryInterface $repository,
        private PriceAvailabilityReadRepositoryInterface $prices,
    ) {}

    public function handle(object $message): ?int
    {
        assert($message instanceof SetAvailabilityCommand);

        if ($message->isAvailable) {
            abort_unless(
                $this->prices->hasPrice($message->storeId, $message->productId),
                422,
                'Сначала назначьте цену товара.',
            );
            $this->repository->include($message->storeId, $message->productId);
        } else {
            $this->repository->exclude($message->storeId, $message->productId);
        }

        return null;
    }
}
