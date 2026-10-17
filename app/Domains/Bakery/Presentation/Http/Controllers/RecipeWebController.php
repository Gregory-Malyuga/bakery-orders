<?php

namespace Domains\Bakery\Presentation\Http\Controllers;

use Domains\Bakery\Application\ProductionCalculator;
use Domains\Bakery\Application\ProductionRecipeStore;
use Domains\Bakery\Application\Repositories\OrderingRepositoryInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RecipeWebController
{
    public function __construct(private readonly OrderingRepositoryInterface $orders, private readonly ProductionCalculator $calculator, private readonly ProductionRecipeStore $store) {}

    public function index(Request $request): View
    {
        $input = $request->validate(['product' => ['nullable', 'integer', 'exists:bakery_products,id']]);
        $products = $this->orders->products();
        $selected = $products->firstWhere('id', (int) ($input['product'] ?? $products->first()?->id));

        return view('bakery.recipes', [
            'products' => $products,
            'selected' => $selected,
            'productRecipe' => $selected ? $this->calculator->productDefinition($selected->name, (int) $selected->id) : null,
            'mainDough' => $this->calculator->doughDefinition('main_dough'),
            'samsaDough' => $this->calculator->doughDefinition('samsa_dough'),
        ]);
    }

    public function saveProduct(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'product' => ['required', 'integer', 'exists:bakery_products,id'],
            'dough_grams' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'ingredients' => ['sometimes', 'array', 'max:100'],
            'ingredients.*.name' => ['required', 'string', 'max:100'],
            'ingredients.*.amount' => ['required', 'numeric', 'gt:0', 'max:100000'],
            'ingredients.*.unit' => ['required', 'in:g,pcs,tbsp'],
        ]);
        $ingredients = $this->ingredients($data['ingredients'] ?? []);
        $this->store->save('product:'.$data['product'], [
            'dough_grams' => isset($data['dough_grams']) ? (float) $data['dough_grams'] : null,
            'ingredients' => $ingredients,
        ]);

        return redirect()->route('bakery.recipes', ['product' => $data['product']])->with('success', 'Рецептура изделия сохранена.');
    }

    public function saveDough(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'kind' => ['required', 'in:main_dough,samsa_dough'],
            'batch_size' => $request->input('kind') === 'samsa_dough'
                ? ['required', 'integer', 'min:1', 'max:100000']
                : ['required', 'numeric', 'gt:0', 'max:100000'],
            'ingredients' => ['required', 'array', 'min:1', 'max:100'],
            'ingredients.*.name' => ['required', 'string', 'max:100'],
            'ingredients.*.amount' => ['required', 'numeric', 'gt:0', 'max:100000'],
        ]);
        $rows = [];
        foreach ($data['ingredients'] as $ingredient) {
            $rows[] = ['name' => $ingredient['name'], 'amount' => $ingredient['amount'], 'unit' => 'g'];
        }
        $this->store->save($data['kind'], [
            'batch_size' => (float) $data['batch_size'],
            'ingredients' => $this->ingredients($rows),
        ]);

        return redirect()->route('bakery.recipes')->with('success', 'Рецептура теста сохранена.');
    }

    public function reset(Request $request): RedirectResponse
    {
        $data = $request->validate(['kind' => ['required', 'in:product,main_dough,samsa_dough'], 'product' => ['required_if:kind,product', 'nullable', 'integer', 'exists:bakery_products,id']]);
        $key = $data['kind'] === 'product' ? 'product:'.$data['product'] : $data['kind'];
        $this->store->reset($key);

        return redirect()->route('bakery.recipes', isset($data['product']) ? ['product' => $data['product']] : [])->with('success', 'Восстановлены исходные нормы из таблицы.');
    }

    /** @param array<int|string, array{name:string,amount:int|float|string,unit:string}> $rows
     * @return list<array{name:string,amount:float,unit:string}>
     */
    private function ingredients(array $rows): array
    {
        $result = [];
        $seen = [];
        foreach ($rows as $row) {
            $name = trim($row['name']);
            if ($name === '') {
                throw ValidationException::withMessages(['ingredients' => 'Укажите название каждого ингредиента.']);
            }
            $key = mb_strtolower($name);
            if (isset($seen[$key])) {
                throw ValidationException::withMessages(['ingredients' => 'Один ингредиент указан дважды.']);
            }
            $seen[$key] = true;
            $result[] = ['name' => $name, 'amount' => (float) $row['amount'], 'unit' => $row['unit']];
        }

        return $result;
    }
}
