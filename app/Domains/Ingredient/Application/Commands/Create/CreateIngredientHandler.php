<?php

namespace Domains\Ingredient\Application\Commands\Create;

use Domains\Ingredient\Application\Repositories\IngredientRepositoryInterface;
use Illuminate\Validation\ValidationException;
use Shared\Bus\CommandHandlerInterface;

readonly class CreateIngredientHandler implements CommandHandlerInterface
{
    public function __construct(private IngredientRepositoryInterface $repository) {}

    public function handle(object $message): ?int
    {
        assert($message instanceof CreateIngredientCommand);

        $name = trim($message->name);
        if ($name === '') {
            throw ValidationException::withMessages(['name' => 'Укажите название ингредиента.']);
        }

        if ($this->repository->existsWithName($name, $message->unit)) {
            throw ValidationException::withMessages([
                'name' => 'Ингредиент с такой единицей уже есть в справочнике.',
            ]);
        }

        $this->repository->create($name, $message->unit);

        return null;
    }
}
