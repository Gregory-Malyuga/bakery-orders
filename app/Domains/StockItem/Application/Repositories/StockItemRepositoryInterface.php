<?php

namespace Domains\StockItem\Application\Repositories;

interface StockItemRepositoryInterface
{
    public function syncCatalog(): void;

    public function createPackaging(string $name, string $unit): void;

    public function findUnit(int $id): ?string;
}
