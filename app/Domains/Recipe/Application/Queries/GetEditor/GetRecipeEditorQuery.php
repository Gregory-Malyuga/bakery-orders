<?php

namespace Domains\Recipe\Application\Queries\GetEditor;

use Shared\Bus\BaseQuery;

readonly class GetRecipeEditorQuery implements BaseQuery
{
    public function __construct(public string $section, public ?int $productId) {}
}
