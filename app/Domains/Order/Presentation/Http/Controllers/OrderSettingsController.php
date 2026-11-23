<?php

namespace Domains\Order\Presentation\Http\Controllers;

use Domains\Order\Application\Commands\SetCutoff\SetOrderCutoffCommand;
use Domains\Order\Application\Commands\SetCutoff\SetOrderCutoffHandler;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

readonly class OrderSettingsController
{
    public function __construct(private SetOrderCutoffHandler $handler) {}

    public function save(Request $request): RedirectResponse
    {
        /** @var array{order_cutoff:string} $data */
        $data = $request->validate(['order_cutoff' => ['required', 'date_format:H:i']]);
        $this->handler->handle(new SetOrderCutoffCommand($data['order_cutoff']));

        return redirect()
            ->route('bakery.catalog', ['tab' => 'settings'])
            ->with('success', 'Время последнего заказа сохранено.');
    }
}
