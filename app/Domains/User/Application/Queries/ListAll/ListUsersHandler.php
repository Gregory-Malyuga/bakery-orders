<?php

namespace Domains\User\Application\Queries\ListAll;

use Domains\User\Application\Repositories\UserRepositoryInterface;
use Domains\User\Domain\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Shared\Bus\QueryHandlerInterface;

readonly class ListUsersHandler implements QueryHandlerInterface
{
    public function __construct(private UserRepositoryInterface $repository) {}

    /** @return LengthAwarePaginator<int, User> */
    public function handle(object $message): LengthAwarePaginator
    {
        assert($message instanceof ListUsersQuery);

        /** @var LengthAwarePaginator<int, User> $result */
        $result = $this->repository->list(
            $message->filters,
            $message->sort,
            $message->pagination,
        );

        return $result;
    }
}
