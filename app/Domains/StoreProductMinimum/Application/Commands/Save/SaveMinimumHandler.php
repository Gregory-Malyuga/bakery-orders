<?php

namespace Domains\StoreProductMinimum\Application\Commands\Save;

use Domains\Product\Application\Repositories\ProductRepositoryInterface;
use Domains\StoreProductMinimum\Application\Repositories\StoreProductMinimumRepositoryInterface;
use Shared\Bus\CommandHandlerInterface;

readonly class SaveMinimumHandler implements CommandHandlerInterface
{
    public function __construct(
        private StoreProductMinimumRepositoryInterface $repository,
        private ProductRepositoryInterface $products,
    ) {}

    public function handle(object $message): ?int
    {
        assert($message instanceof SaveMinimumCommand);

        if ($message->minimum === null) {
            $this->repository->delete($message->storeId, $message->productId);

            return null;
        }

        $mode = $this->products->packagingMode($message->productId);
        $packageMinimum = match ($mode) {
            'mixed' => 3,
            'pair' => 2,
            default => 1,
        };
        $this->repository->save(
            $message->storeId,
            $message->productId,
            max($message->minimum, $packageMinimum),
        );

        return null;
    }
}
