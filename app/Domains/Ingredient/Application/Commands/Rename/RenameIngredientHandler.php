<?php

namespace Domains\Ingredient\Application\Commands\Rename;

use Domains\Ingredient\Application\Repositories\IngredientRepositoryInterface;
use Illuminate\Validation\ValidationException;
use Shared\Bus\CommandHandlerInterface;

readonly class RenameIngredientHandler implements CommandHandlerInterface
{
    public function __construct(private IngredientRepositoryInterface $repository) {}

    public function handle(object $message): ?int
    {
        assert($message instanceof RenameIngredientCommand);

        $item = $this->repository->find($message->id);
        abort_if($item === null, 404);

        $name = trim($message->name);
        if ($name === '') {
            throw ValidationException::withMessages(['name' => 'Укажите название ингредиента.']);
        }
        if ($this->repository->existsWithName($name, $item->unit, $message->id)) {
            throw ValidationException::withMessages([
                'name' => 'Ингредиент с такой единицей уже есть в справочнике.',
            ]);
        }

        $this->repository->rename($message->id, $name);

        return null;
    }
}
