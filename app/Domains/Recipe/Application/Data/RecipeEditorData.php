<?php

namespace Domains\Recipe\Application\Data;

use Shared\Http\Data\BaseData;

class RecipeEditorData extends BaseData
{
    /** @param array<string, mixed> $data */
    public function __construct(public readonly array $data) {}
}
