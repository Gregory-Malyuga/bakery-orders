<?php

namespace Domains\Bakery\Presentation\Http\Controllers;

use Domains\Bakery\Application\IngredientCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class IngredientCatalogWebController
{
    public function __construct(private readonly IngredientCatalog $catalog) {}

    public function index(): View
    {
        return view('bakery.ingredients', ['ingredients' => $this->catalog->all()]);
    }

    public function create(Request $request): RedirectResponse
    {
        /** @var array{name:string,unit:string} $data */
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'unit' => ['required', 'in:g,pcs,tbsp'],
        ]);
        $name = trim($data['name']);
        $this->ensureUnique($name, $data['unit']);
        DB::table('bakery_ingredients')->insert([
            'name' => $name, 'unit' => $data['unit'], 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return redirect()->route('bakery.ingredients')->with('success', 'Ингредиент добавлен.');
    }

    public function rename(Request $request, int $ingredient): RedirectResponse
    {
        /** @var array{name:string} $data */
        $data = $request->validate(['name' => ['required', 'string', 'max:100']]);
        /** @var object{unit:string}|null $item */
        $item = DB::table('bakery_ingredients')->find($ingredient);
        abort_if($item === null, 404);
        $name = trim($data['name']);
        $this->ensureUnique($name, $item->unit, $ingredient);
        DB::table('bakery_ingredients')->where('id', $ingredient)->update(['name' => $name, 'updated_at' => now()]);

        return redirect()->route('bakery.ingredients')->with('success', 'Название обновлено во всех рецептурах и расчётах.');
    }

    public function toggle(int $ingredient): RedirectResponse
    {
        /** @var object{is_active:bool}|null $item */
        $item = DB::table('bakery_ingredients')->find($ingredient);
        abort_if($item === null, 404);
        DB::table('bakery_ingredients')->where('id', $ingredient)->update([
            'is_active' => ! $item->is_active, 'updated_at' => now(),
        ]);

        return redirect()->route('bakery.ingredients')->with('success', $item->is_active
            ? 'Ингредиент скрыт для новых рецептур. В существующих расчётах он сохранён.'
            : 'Ингредиент снова доступен в рецептурах.');
    }

    private function ensureUnique(string $name, string $unit, ?int $exceptId = null): void
    {
        if ($name === '') {
            throw ValidationException::withMessages(['name' => 'Укажите название ингредиента.']);
        }
        $query = DB::table('bakery_ingredients')->where('unit', $unit)->whereRaw('lower(name) = ?', [mb_strtolower($name)]);
        if ($exceptId !== null) {
            $query->where('id', '!=', $exceptId);
        }
        if ($query->exists()) {
            throw ValidationException::withMessages(['name' => 'Ингредиент с такой единицей уже есть в справочнике.']);
        }
    }
}
