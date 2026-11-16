<?php

namespace Domains\Recipe\Application\Commands\Reset;

use Domains\Recipe\Application\RecipeEditorService;
use Shared\Bus\CommandHandlerInterface;

readonly class ResetRecipeHandler implements CommandHandlerInterface
{
    public function __construct(private RecipeEditorService $service) {}

    public function handle(object $message): ?int
    {
        assert($message instanceof ResetRecipeCommand);

        $this->service->reset($message->data);

        return null;
    }
}
