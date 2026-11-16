<?php

namespace Domains\Ingredient\Application\Queries\ListAll;

use Domains\Ingredient\Application\Repositories\IngredientRepositoryInterface;
use Illuminate\Support\Collection;
use Shared\Bus\QueryHandlerInterface;

readonly class ListIngredientsHandler implements QueryHandlerInterface
{
    public function __construct(private IngredientRepositoryInterface $repository) {}

    /** @return Collection<int, \stdClass> */
    public function handle(object $message): Collection
    {
        assert($message instanceof ListIngredientsQuery);

        return $this->repository->all();
    }
}
