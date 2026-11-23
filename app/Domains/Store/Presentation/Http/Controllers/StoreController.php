<?php

namespace Domains\Store\Presentation\Http\Controllers;

use Domains\Store\Application\Commands\Save\SaveStoreCommand;
use Domains\Store\Application\Commands\Save\SaveStoreHandler;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

readonly class StoreController
{
    public function __construct(private SaveStoreHandler $save) {}

    public function save(Request $request): RedirectResponse
    {
        /**
         * @var array{id?:int|string,name:string,address:string,is_active:bool|int|string,
         *     is_cashless?:bool|int|string,discount_percent?:int|string|null} $data
         */
        $data = $request->validate([
            'id' => ['nullable', 'integer', 'exists:bakeries,id'],
            'name' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
            'is_cashless' => ['sometimes', 'required', 'boolean'],
            'discount_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
        ]);

        $this->save->handle(
            new SaveStoreCommand(
                isset($data['id']) ? (int) $data['id'] : null,
                $data['name'],
                $data['address'],
                (bool) $data['is_active'],
                isset($data['is_cashless']) ? (bool) $data['is_cashless'] : null,
                (int) ($data['discount_percent'] ?? 0),
            ),
        );

        return back()->with('success', 'Магазин сохранён.');
    }
}
