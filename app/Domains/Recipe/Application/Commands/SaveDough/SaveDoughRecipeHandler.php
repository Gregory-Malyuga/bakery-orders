<?php

namespace Domains\Recipe\Application\Commands\SaveDough;

use Domains\Recipe\Application\RecipeEditorService;
use Shared\Bus\CommandHandlerInterface;

readonly class SaveDoughRecipeHandler implements CommandHandlerInterface
{
    public function __construct(private RecipeEditorService $service) {}

    public function handle(object $message): ?int
    {
        assert($message instanceof SaveDoughRecipeCommand);

        $this->service->saveDough($message->data);

        return null;
    }
}
