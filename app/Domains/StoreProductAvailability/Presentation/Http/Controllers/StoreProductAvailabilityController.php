<?php

namespace Domains\StoreProductAvailability\Presentation\Http\Controllers;

use Domains\StoreProductAvailability\Application\Commands\Set\SetAvailabilityCommand;
use Domains\StoreProductAvailability\Application\Commands\Set\SetAvailabilityHandler;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

readonly class StoreProductAvailabilityController
{
    public function __construct(private SetAvailabilityHandler $handler) {}

    public function save(Request $request): RedirectResponse
    {
        /** @var array{store:int|string,product:int|string,is_available:bool|int|string} $data */
        $data = $request->validate([
            'store' => ['required', 'integer', 'exists:bakeries,id'],
            'product' => ['required', 'integer', 'exists:bakery_products,id'],
            'is_available' => ['required', 'boolean'],
        ]);

        $storeId = (int) $data['store'];
        $this->handler->handle(new SetAvailabilityCommand(
            $storeId,
            (int) $data['product'],
            (bool) $data['is_available'],
        ));

        return redirect()->route('bakery.catalog', ['tab' => 'prices', 'store' => $storeId])->with('success', 'Ассортимент магазина сохранён.');
    }
}
