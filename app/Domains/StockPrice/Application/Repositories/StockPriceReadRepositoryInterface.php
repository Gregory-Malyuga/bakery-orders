<?php

namespace Domains\StockPrice\Application\Repositories;

use Illuminate\Support\Collection;

interface StockPriceReadRepositoryInterface
{
    public function ensureThrough(string $date): void;

    /** @return Collection<int|string, \stdClass> */
    public function forDate(string $date): Collection;

    /**
     * @return list<array{date:string,item:string,unit:string,balance_milli:int,
     *     price_kopecks:int|null,measure_milli:int|null,measure_unit:string|null,value_kopecks:int|null}>
     */
    public function dailyRows(string $from, string $to): array;
}
