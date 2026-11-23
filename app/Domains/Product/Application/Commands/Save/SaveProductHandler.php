<?php

namespace Domains\Product\Application\Commands\Save;

use Domains\Product\Application\Repositories\ProductRepositoryInterface;
use Shared\Bus\CommandHandlerInterface;

readonly class SaveProductHandler implements CommandHandlerInterface
{
    public function __construct(private ProductRepositoryInterface $repository) {}

    public function handle(object $message): ?int
    {
        assert($message instanceof SaveProductCommand);

        $mode =
            $message->packagingMode ??
            ($message->id !== null ? $this->repository->packagingMode($message->id) : 'mixed');

        $packageMinimum = match ($mode) {
            'mixed' => 3,
            'pair' => 2,
            default => 1,
        };

        $changes = [
            'name' => $message->name,
            'sort_order' => $message->sortOrder,
            'minimum_quantity' => max($message->minimumQuantity, $packageMinimum),
            'is_active' => $message->isActive,
        ];

        if ($message->packagingMode !== null) {
            $changes['packaging_mode'] = $message->packagingMode;
        }

        $this->repository->save($message->id, $changes, $packageMinimum);

        return null;
    }
}
