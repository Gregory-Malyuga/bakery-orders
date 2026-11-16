<?php

namespace Domains\StoreAccount\Presentation\Http\Controllers;

use Domains\StoreAccount\Application\Commands\Save\SaveStoreAccountCommand;
use Domains\StoreAccount\Application\Commands\Save\SaveStoreAccountHandler;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

readonly class StoreAccountController
{
    public function __construct(private SaveStoreAccountHandler $handler) {}

    public function save(Request $request): RedirectResponse
    {
        /** @var array{id?:int|string,stores?:list<int|string>,name:string,email:string,password?:string,is_active:bool|int|string} $data */
        $data = $request->validate([
            'id' => ['nullable', 'integer', 'exists:users,id'],
            'stores' => ['sometimes', 'array'],
            'stores.*' => ['required', 'integer', 'distinct', 'exists:bakeries,id'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,'.(is_scalar($request->input('id')) ? $request->input('id') : 'NULL')],
            'password' => ['nullable', 'string', 'min:10'],
            'is_active' => ['required', 'boolean'],
        ]);
        $this->handler->handle(new SaveStoreAccountCommand(
            isset($data['id']) ? (int) $data['id'] : null,
            array_map('intval', $data['stores'] ?? []),
            $data['name'],
            $data['email'],
            $data['password'] ?? null,
            (bool) $data['is_active'],
        ));

        return back()->with('success', 'Аккаунт сохранён.');
    }
}
