<?php

namespace Domains\StockPriceChange\Application\Repositories;

interface StockPriceChangeRepositoryInterface
{
    /** @return object{price_kopecks:int,measure_milli:int,measure_unit:string}|null */
    public function latestForDate(int $itemId, string $date): ?object;

    /** @param array<string, mixed> $values */
    public function record(array $values): void;
}
