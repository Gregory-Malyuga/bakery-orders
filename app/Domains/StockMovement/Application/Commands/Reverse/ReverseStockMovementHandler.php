<?php

namespace Domains\StockMovement\Application\Commands\Reverse;

use Domains\StockMovement\Application\StockMovementService;
use Illuminate\Validation\ValidationException;
use Shared\Bus\CommandHandlerInterface;

readonly class ReverseStockMovementHandler implements CommandHandlerInterface
{
    public function __construct(private StockMovementService $ledger) {}

    public function handle(object $message): ?int
    {
        assert($message instanceof ReverseStockMovementCommand);

        $reason = trim($message->reason);
        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => 'Укажите причину исправления.']);
        }

        $this->ledger->reverse($message->movementId, $reason, $message->actorId);

        return null;
    }
}
