<?php

namespace Domains\Bakery\Application\Queries\ListAll;

use Domains\Bakery\Application\Repositories\BakeryRepositoryInterface;
use Domains\Bakery\Domain\Models\Bakery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Shared\Bus\QueryHandlerInterface;

readonly class ListBakerysHandler implements QueryHandlerInterface
{
    public function __construct(private readonly BakeryRepositoryInterface $repository) {}

    /** @return LengthAwarePaginator<int, Bakery> */
    public function handle(object $message): LengthAwarePaginator
    {
        assert($message instanceof ListBakerysQuery);

        /** @var LengthAwarePaginator<int, Bakery> $result */
        $result = $this->repository->list(
            $message->filters,
            $message->sort,
            $message->pagination,
        );

        return $result;
    }
}
