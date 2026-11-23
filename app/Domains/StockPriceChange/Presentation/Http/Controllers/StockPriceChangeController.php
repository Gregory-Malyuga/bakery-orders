<?php

namespace Domains\StockPriceChange\Presentation\Http\Controllers;

use Domains\StockPriceChange\Application\Commands\Save\SaveStockPriceCommand;
use Domains\StockPriceChange\Application\Commands\Save\SaveStockPriceHandler;
use Domains\User\Domain\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

readonly class StockPriceChangeController
{
    public function __construct(private SaveStockPriceHandler $handler) {}

    public function save(Request $request): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        /** @var array{item:int,price:string,measure:string,custom_quantity?:string,custom_unit?:string} $data */
        $data = $request->validate([
            'item' => ['required', 'integer', 'exists:bakery_stock_items,id'],
            'price' => ['required', 'string', 'max:20'],
            'measure' => ['required', 'in:1g,70g,100g,1kg,1ml,100ml,1l,1unit,custom'],
            'custom_quantity' => ['required_if:measure,custom', 'nullable', 'string', 'max:20'],
            'custom_unit' => [
                'required_if:measure,custom',
                'nullable',
                'in:g,kg,ml,l,pcs,tbsp,m,roll',
            ],
        ]);

        $this->handler->handle(
            new SaveStockPriceCommand(
                $data['item'],
                $data['price'],
                $data['measure'],
                $data['custom_quantity'] ?? null,
                $data['custom_unit'] ?? null,
                (int) $actor->id,
            ),
        );

        return redirect()->route('bakery.stock')->with('success', 'Цена на завтра сохранена.');
    }
}
