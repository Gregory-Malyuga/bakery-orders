<?php

namespace Domains\StockMovement\Presentation\Http\Controllers;

use Domains\StockMovement\Application\Commands\Record\RecordStockMovementCommand;
use Domains\StockMovement\Application\Commands\Record\RecordStockMovementHandler;
use Domains\StockMovement\Application\Commands\Reverse\ReverseStockMovementCommand;
use Domains\StockMovement\Application\Commands\Reverse\ReverseStockMovementHandler;
use Domains\User\Domain\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

readonly class StockMovementController
{
    public function __construct(
        private RecordStockMovementHandler $record,
        private ReverseStockMovementHandler $reverse,
    ) {}

    public function record(Request $request): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        /** @var array{item:int,kind:string,quantity:string,reason:string} $data */
        $data = $request->validate([
            'item' => ['required', 'integer', 'exists:bakery_stock_items,id'],
            'kind' => ['required', 'in:opening,receipt,consumption,writeoff,return,inventory'],
            'quantity' => ['required', 'string', 'max:20'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $this->record->handle(
            new RecordStockMovementCommand(
                $data['item'],
                $data['kind'],
                $data['quantity'],
                $data['reason'],
                (int) $actor->id,
            ),
        );

        return redirect()->route('bakery.stock')->with('success', 'Движение записано.');
    }

    public function reverse(Request $request, int $movement): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        /** @var array{reason:string} $data */
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);

        $this->reverse->handle(
            new ReverseStockMovementCommand($movement, $data['reason'], (int) $actor->id),
        );

        return redirect()
            ->route('bakery.stock')
            ->with('success', 'Исправление записано отдельным движением.');
    }
}
