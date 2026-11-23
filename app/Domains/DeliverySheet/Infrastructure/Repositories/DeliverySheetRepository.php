<?php

namespace Domains\DeliverySheet\Infrastructure\Repositories;

use Domains\DeliverySheet\Application\Repositories\DeliverySheetRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class DeliverySheetRepository implements DeliverySheetRepositoryInterface
{
    /** @return Collection<int, \stdClass> */
    public function sheets(): Collection
    {
        return DB::table('bakery_delivery_sheets')->orderByDesc('id')->limit(100)->get();
    }

    /** @return Collection<int, \stdClass> */
    public function prints(): Collection
    {
        return DB::table('bakery_delivery_sheet_prints')->orderByDesc('id')->limit(100)->get();
    }

    /** @return object{snapshot:string}|null */
    public function sheet(int $id): ?object
    {
        /** @var object{snapshot:string}|null $row */
        $row = DB::table('bakery_delivery_sheets')->find($id);

        return $row;
    }

    /** @return object{snapshot:string,printed_at:string}|null */
    public function printRecord(int $id): ?object
    {
        /** @var object{snapshot:string,printed_at:string}|null $row */
        $row = DB::table('bakery_delivery_sheet_prints')->find($id);

        return $row;
    }

    /** @param array<string, mixed> $snapshot */
    public function createSheet(string $date, array $snapshot): int
    {
        return (int) DB::table('bakery_delivery_sheets')->insertGetId([
            'delivery_date' => $date,
            'snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @param array<string, mixed> $snapshot */
    public function updateSheet(int $id, string $date, array $snapshot): void
    {
        DB::table('bakery_delivery_sheets')
            ->where('id', $id)
            ->update([
                'delivery_date' => $date,
                'snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR),
                'updated_at' => now(),
            ]);
    }

    public function deleteSheet(int $id): int
    {
        return DB::table('bakery_delivery_sheets')->where('id', $id)->delete();
    }

    /** @param array<string, mixed> $snapshot */
    public function createPrint(string $date, int $sourceId, array $snapshot): int
    {
        return (int) DB::table('bakery_delivery_sheet_prints')->insertGetId([
            'delivery_date' => $date,
            'source_sheet_id' => $sourceId,
            'snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR),
            'printed_at' => now(),
        ]);
    }
}
