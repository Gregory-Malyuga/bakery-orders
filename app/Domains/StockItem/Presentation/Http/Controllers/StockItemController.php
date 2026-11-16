<?php

namespace Domains\StockItem\Presentation\Http\Controllers;

use Domains\StockItem\Application\Commands\CreatePackaging\CreatePackagingCommand;
use Domains\StockItem\Application\Commands\CreatePackaging\CreatePackagingHandler;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

readonly class StockItemController
{
    public function __construct(private CreatePackagingHandler $handler) {}

    public function createPackaging(Request $request): RedirectResponse
    {
        /** @var array{name:string,unit:string} $data */
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'unit' => ['required', 'in:pcs,g,m,roll'],
        ]);

        $this->handler->handle(new CreatePackagingCommand($data['name'], $data['unit']));

        return redirect()->route('bakery.stock')->with('success', 'Упаковка добавлена.');
    }
}
