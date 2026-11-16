<?php

namespace Domains\StockPrice\Application;

use Domains\StockPrice\Application\Repositories\StockPriceReadRepositoryInterface;
use Illuminate\Support\Collection;

readonly class StockPrices
{
    public function __construct(private StockPriceReadRepositoryInterface $repository) {}

    public function ensureThrough(string $date): void
    {
        $this->repository->ensureThrough($date);
    }

    /** @return Collection<int|string, \stdClass> */
    public function forDate(string $date): Collection
    {
        return $this->repository->forDate($date);
    }

    public static function value(int $balanceMilli, int $priceKopecks, int $measureMilli, string $stockUnit, string $measureUnit): int
    {
        $stockFactor = in_array($stockUnit, ['kg', 'l'], true) ? 1000 : 1;

        return (int) round($balanceMilli * $stockFactor * $priceKopecks / $measureMilli);
    }

    /** @return list<array{date:string,item:string,unit:string,balance_milli:int,price_kopecks:int|null,measure_milli:int|null,measure_unit:string|null,value_kopecks:int|null}> */
    public function dailyRows(string $from, string $to): array
    {
        return $this->repository->dailyRows($from, $to);
    }
}
