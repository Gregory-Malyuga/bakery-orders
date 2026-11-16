<?php

namespace Domains\Catalog\Application\Data;

use Shared\Http\Data\BaseData;

class CatalogViewData extends BaseData
{
    /** @param array<string, mixed> $view */
    public function __construct(public readonly array $view) {}
}
