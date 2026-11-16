<?php

namespace Domains\Product\Application\Repositories;

interface ProductRepositoryInterface
{
    public function packagingMode(int $id): ?string;

    /** @param array<string, mixed> $changes */
    public function save(?int $id, array $changes, int $minimumForPackaging): void;
}
