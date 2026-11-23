<?php

namespace Domains\StoreAccount\Application\Commands\Save;

use Domains\StoreAccount\Application\Repositories\StoreAccountRepositoryInterface;
use Shared\Bus\CommandHandlerInterface;

readonly class SaveStoreAccountHandler implements CommandHandlerInterface
{
    public function __construct(private StoreAccountRepositoryInterface $repository) {}

    public function handle(object $message): ?int
    {
        assert($message instanceof SaveStoreAccountCommand);

        $this->repository->saveAccount(
            $message->id,
            $message->storeIds,
            $message->name,
            $message->email,
            $message->password,
            $message->isActive,
        );

        return null;
    }
}
