<?php

namespace Domains\StockItem\Application\Repositories;

interface StockDashboardReadRepositoryInterface
{
    /** @return array<string, mixed> */
    public function data(string $date): array;
}
