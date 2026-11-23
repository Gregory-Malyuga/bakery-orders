<?php

namespace Domains\Store\Infrastructure\Repositories;

use Domains\Store\Application\Repositories\StoreAnalyticsReadRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class StoreAnalyticsReadRepository implements StoreAnalyticsReadRepositoryInterface
{
    /** @param list<int> $excludedStoreIds
     * @return list<string>
     */
    public function activeStoreNamesWithoutOrders(array $excludedStoreIds, ?int $storeId): array
    {
        $query = DB::table('bakeries')->where('is_active', true);
        if ($storeId !== null) {
            $query->where('id', $storeId);
        }
        if ($excludedStoreIds !== []) {
            $query->whereNotIn('id', $excludedStoreIds);
        }

        $names = [];
        foreach ($query->orderBy('name')->pluck('name') as $name) {
            if (is_string($name)) {
                $names[] = $name;
            }
        }

        return $names;
    }

    /** @return Collection<int, \stdClass> */
    public function storeOptions(): Collection
    {
        return DB::table('bakeries')
            ->orderBy('name')
            ->get(['id', 'name']);
    }
}
