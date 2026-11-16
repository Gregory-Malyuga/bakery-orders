<?php

namespace Domains\Recipe\Application\Commands\SaveProduct;

use Domains\Recipe\Application\RecipeEditorService;
use Shared\Bus\CommandHandlerInterface;

readonly class SaveProductRecipeHandler implements CommandHandlerInterface
{
    public function __construct(private RecipeEditorService $service) {}

    public function handle(object $message): ?int
    {
        assert($message instanceof SaveProductRecipeCommand);

        $this->service->saveProduct($message->data);

        return null;
    }
}
