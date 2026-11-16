<?php

namespace Domains\StoreGroup\Application\Queries\ListAll;

use Domains\StoreGroup\Application\Data\StoreGroupsData;
use Domains\StoreGroup\Application\Repositories\StoreGroupRepositoryInterface;
use Shared\Bus\QueryHandlerInterface;

readonly class ListStoreGroupsHandler implements QueryHandlerInterface
{
    public function __construct(private StoreGroupRepositoryInterface $repository) {}

    public function handle(object $message): StoreGroupsData
    {
        assert($message instanceof ListStoreGroupsQuery);

        return new StoreGroupsData($this->repository->groups(), $this->repository->members());
    }
}
