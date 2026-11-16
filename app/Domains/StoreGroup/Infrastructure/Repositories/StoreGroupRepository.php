<?php

namespace Domains\StoreGroup\Infrastructure\Repositories;

use Domains\StoreGroup\Application\Repositories\StoreGroupRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class StoreGroupRepository implements StoreGroupRepositoryInterface
{
    /** @return Collection<int, \stdClass> */
    public function groups(): Collection
    {
        return DB::table('bakery_store_groups')->orderBy('name')->get();
    }

    /** @return Collection<int|string, Collection<int, \stdClass>> */
    public function members(): Collection
    {
        return DB::table('bakery_store_group_members')->get()->groupBy('group_id');
    }

    public function save(?int $id, string $name, bool $showInInvoice, bool $excludeFromLabels, array $storeIds): int
    {
        return DB::transaction(static function () use ($id, $name, $showInInvoice, $excludeFromLabels, $storeIds): int {
            $values = [
                'name' => trim($name),
                'show_in_invoice' => $showInInvoice,
                'exclude_from_labels' => $excludeFromLabels,
                'updated_at' => now(),
            ];

            if ($id !== null) {
                DB::table('bakery_store_groups')->where('id', $id)->update($values);
            } else {
                $id = (int) DB::table('bakery_store_groups')->insertGetId($values + ['created_at' => now()]);
            }

            DB::table('bakery_store_group_members')->where('group_id', $id)->delete();
            $members = array_map(static fn (int $storeId): array => [
                'group_id' => $id,
                'bakery_id' => $storeId,
            ], $storeIds);

            if ($members !== []) {
                DB::table('bakery_store_group_members')->insert($members);
            }

            return $id;
        });
    }
}
