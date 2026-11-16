<?php

namespace Domains\Recipe\Application\Queries\GetEditor;

use Domains\Recipe\Application\Data\RecipeEditorData;
use Domains\Recipe\Application\RecipeEditorService;
use Shared\Bus\QueryHandlerInterface;

readonly class GetRecipeEditorHandler implements QueryHandlerInterface
{
    public function __construct(private RecipeEditorService $service) {}

    public function handle(object $message): RecipeEditorData
    {
        assert($message instanceof GetRecipeEditorQuery);

        return new RecipeEditorData($this->service->editor($message->section, $message->productId));
    }
}
