<?php

namespace Domains\StockMovement\Application\Commands\Record;

use Domains\StockMovement\Application\StockMovementService;
use Illuminate\Validation\ValidationException;
use Shared\Bus\CommandHandlerInterface;

readonly class RecordStockMovementHandler implements CommandHandlerInterface
{
    public function __construct(private StockMovementService $ledger) {}

    public function handle(object $message): ?int
    {
        assert($message instanceof RecordStockMovementCommand);

        $quantity = $this->ledger->quantity($message->quantity);
        $reason = trim($message->reason);
        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => 'Укажите основание движения.']);
        }

        if ($message->kind === 'inventory') {
            $this->ledger->inventory($message->itemId, $quantity, $reason, $message->actorId);
        } else {
            $this->ledger->record($message->itemId, $message->kind, $quantity, $reason, $message->actorId);
        }

        return null;
    }
}
