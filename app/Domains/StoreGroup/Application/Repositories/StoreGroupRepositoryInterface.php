<?php

namespace Domains\StoreGroup\Application\Repositories;

use Illuminate\Support\Collection;

interface StoreGroupRepositoryInterface
{
    /** @return Collection<int, \stdClass> */
    public function groups(): Collection;

    /** @return Collection<int|string, Collection<int, \stdClass>> */
    public function members(): Collection;

    /** @param list<int> $storeIds */
    public function save(
        ?int $id,
        string $name,
        bool $showInInvoice,
        bool $excludeFromLabels,
        array $storeIds,
    ): int;
}
