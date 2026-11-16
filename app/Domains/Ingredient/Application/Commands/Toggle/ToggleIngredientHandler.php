<?php

namespace Domains\Ingredient\Application\Commands\Toggle;

use Domains\Ingredient\Application\Repositories\IngredientRepositoryInterface;
use Shared\Bus\CommandHandlerInterface;

readonly class ToggleIngredientHandler implements CommandHandlerInterface
{
    public function __construct(private IngredientRepositoryInterface $repository) {}

    public function handle(object $message): ?int
    {
        assert($message instanceof ToggleIngredientCommand);

        $item = $this->repository->find($message->id);
        abort_if($item === null, 404);
        $this->repository->setActive($message->id, ! $item->is_active);

        return (int) $item->is_active;
    }
}
