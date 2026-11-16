<?php

namespace Domains\Store\Application\Repositories;

interface StoreRepositoryInterface
{
    /** @param array<string, mixed> $attributes */
    public function save(?int $id, array $attributes): void;
}
