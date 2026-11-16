<?php

namespace Domains\Bakery\Application\Queries\FindById;

use Domains\Bakery\Application\Repositories\BakeryRepositoryInterface;
use Domains\Bakery\Domain\Models\Bakery;
use Shared\Bus\QueryHandlerInterface;

readonly class FindBakeryByIdHandler implements QueryHandlerInterface
{
    public function __construct(private readonly BakeryRepositoryInterface $repository) {}

    public function handle(object $message): Bakery
    {
        assert($message instanceof FindBakeryByIdQuery);

        /** @var Bakery $record */
        $record = $this->repository->findOrFail($message->id);

        return $record;
    }
}
