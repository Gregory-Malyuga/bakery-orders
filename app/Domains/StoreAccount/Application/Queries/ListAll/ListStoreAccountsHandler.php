<?php

namespace Domains\StoreAccount\Application\Queries\ListAll;

use Domains\StoreAccount\Application\Data\StoreAccountsData;
use Domains\StoreAccount\Application\Repositories\StoreAccountRepositoryInterface;
use Shared\Bus\QueryHandlerInterface;

readonly class ListStoreAccountsHandler implements QueryHandlerInterface
{
    public function __construct(private StoreAccountRepositoryInterface $repository) {}

    public function handle(object $message): StoreAccountsData
    {
        assert($message instanceof ListStoreAccountsQuery);

        return new StoreAccountsData($this->repository->accounts());
    }
}
