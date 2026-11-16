<?php

namespace Domains\DeliverySheet\Application\Repositories;

use Illuminate\Support\Collection;

interface DeliverySheetRepositoryInterface
{
    /** @return Collection<int, \stdClass> */
    public function sheets(): Collection;

    /** @return Collection<int, \stdClass> */
    public function prints(): Collection;

    /** @return object{snapshot:string}|null */
    public function sheet(int $id): ?object;

    /** @return object{snapshot:string,printed_at:string}|null */
    public function printRecord(int $id): ?object;

    /** @param array<string, mixed> $snapshot */
    public function createSheet(string $date, array $snapshot): int;

    /** @param array<string, mixed> $snapshot */
    public function updateSheet(int $id, string $date, array $snapshot): void;

    public function deleteSheet(int $id): int;

    /** @param array<string, mixed> $snapshot */
    public function createPrint(string $date, int $sourceId, array $snapshot): int;

}
