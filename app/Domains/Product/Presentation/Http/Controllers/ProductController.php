<?php

namespace Domains\Product\Presentation\Http\Controllers;

use Domains\Product\Application\Commands\Save\SaveProductCommand;
use Domains\Product\Application\Commands\Save\SaveProductHandler;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

readonly class ProductController
{
    public function __construct(private SaveProductHandler $save) {}

    public function save(Request $request): RedirectResponse
    {
        /** @var array{id?:int|string,name:string,sort_order:int|string,minimum_quantity:int|string,packaging_mode?:string,is_active:bool|int|string} $data */
        $data = $request->validate([
            'id' => ['nullable', 'integer', 'exists:bakery_products,id'],
            'name' => ['required', 'string', 'max:255'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'minimum_quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'packaging_mode' => ['nullable', 'in:single,pair,mixed'],
            'is_active' => ['required', 'boolean'],
        ]);

        $this->save->handle(new SaveProductCommand(
            isset($data['id']) ? (int) $data['id'] : null,
            $data['name'],
            (int) $data['sort_order'],
            (int) $data['minimum_quantity'],
            $data['packaging_mode'] ?? null,
            (bool) $data['is_active'],
        ));

        return back()->with('success', 'Товар сохранён.');
    }
}
