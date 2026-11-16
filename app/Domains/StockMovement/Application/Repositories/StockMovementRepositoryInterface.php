<?php

namespace Domains\StockMovement\Application\Repositories;

interface StockMovementRepositoryInterface
{
    public function reverse(int $movementId, string $reason, int $actorId): void;

    public function append(
        int $itemId,
        string $kind,
        ?int $delta,
        string $reason,
        int $actorId,
        ?int $counted = null,
        ?int $reversesId = null,
    ): void;
}
