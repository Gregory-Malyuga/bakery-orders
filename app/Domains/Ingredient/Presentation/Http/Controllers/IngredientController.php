<?php

namespace Domains\Ingredient\Presentation\Http\Controllers;

use Domains\Ingredient\Application\Commands\Create\CreateIngredientCommand;
use Domains\Ingredient\Application\Commands\Create\CreateIngredientHandler;
use Domains\Ingredient\Application\Commands\Rename\RenameIngredientCommand;
use Domains\Ingredient\Application\Commands\Rename\RenameIngredientHandler;
use Domains\Ingredient\Application\Commands\Toggle\ToggleIngredientCommand;
use Domains\Ingredient\Application\Commands\Toggle\ToggleIngredientHandler;
use Domains\Ingredient\Application\Queries\ListAll\ListIngredientsHandler;
use Domains\Ingredient\Application\Queries\ListAll\ListIngredientsQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class IngredientController
{
    public function __construct(
        private readonly ListIngredientsHandler $list,
        private readonly CreateIngredientHandler $create,
        private readonly RenameIngredientHandler $rename,
        private readonly ToggleIngredientHandler $toggle,
    ) {}

    public function index(): View
    {
        return view('bakery.ingredients', ['ingredients' => $this->list->handle(new ListIngredientsQuery)]);
    }

    public function create(Request $request): RedirectResponse
    {
        /** @var array{name:string,unit:string} $data */
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'unit' => ['required', 'in:g,pcs,tbsp'],
        ]);

        $this->create->handle(new CreateIngredientCommand($data['name'], $data['unit']));

        return redirect()->route('bakery.ingredients')->with('success', 'Ингредиент добавлен.');
    }

    public function rename(Request $request, int $ingredient): RedirectResponse
    {
        /** @var array{name:string} $data */
        $data = $request->validate(['name' => ['required', 'string', 'max:100']]);

        $this->rename->handle(new RenameIngredientCommand($ingredient, $data['name']));

        return redirect()->route('bakery.ingredients')->with('success', 'Название обновлено во всех рецептурах и расчётах.');
    }

    public function toggle(int $ingredient): RedirectResponse
    {
        $wasActive = $this->toggle->handle(new ToggleIngredientCommand($ingredient));

        return redirect()->route('bakery.ingredients')->with('success', $wasActive
            ? 'Ингредиент скрыт для новых рецептур. В существующих расчётах он сохранён.'
            : 'Ингредиент снова доступен в рецептурах.');
    }
}
