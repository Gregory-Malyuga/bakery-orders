<?php

namespace Domains\Bakery\Application\Data;

use Shared\Http\Data\BaseData;

class BakeryResource extends BaseData
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly ?string $created_at = null,
        public readonly ?string $updated_at = null,
    ) {}
}
