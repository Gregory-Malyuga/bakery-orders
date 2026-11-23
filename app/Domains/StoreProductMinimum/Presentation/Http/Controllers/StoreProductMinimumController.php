<?php

namespace Domains\StoreProductMinimum\Presentation\Http\Controllers;

use Domains\StoreProductMinimum\Application\Commands\Save\SaveMinimumCommand;
use Domains\StoreProductMinimum\Application\Commands\Save\SaveMinimumHandler;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

readonly class StoreProductMinimumController
{
    public function __construct(private SaveMinimumHandler $handler) {}

    public function save(Request $request): RedirectResponse
    {
        /** @var array{store:int|string,product:int|string,minimum_quantity?:int|string|null} $data */
        $data = $request->validate([
            'store' => ['required', 'integer', 'exists:bakeries,id'],
            'product' => ['required', 'integer', 'exists:bakery_products,id'],
            'minimum_quantity' => ['nullable', 'integer', 'min:1', 'max:100000'],
        ]);

        $this->handler->handle(
            new SaveMinimumCommand(
                (int) $data['store'],
                (int) $data['product'],
                isset($data['minimum_quantity']) ? (int) $data['minimum_quantity'] : null,
            ),
        );

        return redirect()
            ->route('bakery.catalog', ['tab' => 'prices', 'store' => $data['store']])
            ->with('success', 'Минимум для магазина сохранён.');
    }
}
