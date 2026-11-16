<?php

namespace Domains\Store\Application\Commands\Save;

use Domains\Store\Application\Repositories\StoreRepositoryInterface;
use Shared\Bus\CommandHandlerInterface;

readonly class SaveStoreHandler implements CommandHandlerInterface
{
    public function __construct(private StoreRepositoryInterface $repository) {}

    public function handle(object $message): ?int
    {
        assert($message instanceof SaveStoreCommand);

        $changes = [
            'name' => $message->name,
            'address' => $message->address,
            'is_active' => $message->isActive,
            'discount_enabled' => $message->discountPercent > 0,
            'discount_percent' => $message->discountPercent,
        ];
        if ($message->isCashless !== null) {
            $changes['is_cashless'] = $message->isCashless;
        }
        $this->repository->save($message->id, $changes);

        return null;
    }
}
