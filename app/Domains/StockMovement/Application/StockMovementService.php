<?php

namespace Domains\StockMovement\Application;

use Domains\StockMovement\Application\Repositories\StockMovementRepositoryInterface;
use Illuminate\Validation\ValidationException;

readonly class StockMovementService
{
    public function __construct(private StockMovementRepositoryInterface $repository) {}

    public function quantity(string $value): int
    {
        $value = str_replace(',', '.', trim($value));
        if (! preg_match('/^\d{1,9}(?:\.\d{1,3})?$/', $value)) {
            throw ValidationException::withMessages(['quantity' => 'Укажите количество до трёх знаков после запятой.']);
        }
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');

        return (int) $whole * 1000 + (int) str_pad($fraction, 3, '0');
    }

    public function record(int $itemId, string $kind, int $quantity, string $reason, int $actorId): void
    {
        if (! in_array($kind, ['opening', 'receipt', 'consumption', 'writeoff', 'return'], true) || $quantity <= 0) {
            throw ValidationException::withMessages(['quantity' => 'Укажите положительное количество.']);
        }

        $delta = in_array($kind, ['consumption', 'writeoff'], true) ? -$quantity : $quantity;
        $this->repository->append($itemId, $kind, $delta, $reason, $actorId);
    }

    public function inventory(int $itemId, int $counted, string $reason, int $actorId): void
    {
        if ($counted < 0) {
            throw ValidationException::withMessages(['quantity' => 'Остаток не может быть отрицательным.']);
        }

        $this->repository->append($itemId, 'inventory', null, $reason, $actorId, $counted);
    }

    public function reverse(int $movementId, string $reason, int $actorId): void
    {
        $this->repository->reverse($movementId, $reason, $actorId);
    }
}
