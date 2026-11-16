<?php

namespace Domains\StoreGroup\Application\Commands\Save;

use Domains\StoreGroup\Application\Repositories\StoreGroupRepositoryInterface;
use Shared\Bus\CommandHandlerInterface;

readonly class SaveStoreGroupHandler implements CommandHandlerInterface
{
    public function __construct(private StoreGroupRepositoryInterface $repository) {}

    public function handle(object $message): ?int
    {
        assert($message instanceof SaveStoreGroupCommand);

        return $this->repository->save(
            $message->id,
            $message->name,
            $message->showInInvoice,
            $message->excludeFromLabels,
            $message->stores,
        );
    }
}
