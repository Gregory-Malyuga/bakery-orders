<?php

namespace Domains\Price\Infrastructure\Repositories;

use Domains\Price\Application\Repositories\PriceHistoryCatalogReadRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class PriceHistoryCatalogReadRepository implements PriceHistoryCatalogReadRepositoryInterface
{
    /** @return Collection<int, \stdClass> */
    public function priceOperations(int $productId): Collection
    {
        return DB::table('bakery_price_operations as o')
            ->join('users as u', 'u.id', '=', 'o.user_id')
            ->where('o.product_id', $productId)
            ->orderByDesc('o.id')
            ->select('o.*', 'u.name as author')
            ->limit(100)
            ->get();
    }

    /** @param Collection<int, \stdClass> $history
     * @return Collection<int|string, Collection<int, \stdClass>>
     */
    public function priceOperationItems(Collection $history): Collection
    {
        /** @var Collection<int|string, Collection<int, \stdClass>> $result */
        $result = DB::table('bakery_price_operation_items as i')
            ->join('bakeries as b', 'b.id', '=', 'i.bakery_id')
            ->whereIn('i.operation_id', $history->pluck('id'))
            ->select('i.*', 'b.name as store_name')
            ->orderBy('i.bakery_id')
            ->get()
            ->groupBy('operation_id');

        return $result;
    }

    /** @param Collection<int, \stdClass> $history
     * @return list<int|string>
     */
    public function reversedOperationIds(Collection $history): array
    {
        /** @var list<int|string> $result */
        $result = DB::table('bakery_price_operations')
            ->whereIn('reverses_id', $history->pluck('id'))
            ->pluck('reverses_id')
            ->all();

        return $result;
    }
}
