<?php

namespace Domains\Bakery\Presentation\Http\Controllers;

use Domains\Bakery\Application\IngredientCatalog;
use Domains\Bakery\Application\ProductionCalculator;
use Domains\Bakery\Application\ProductionRecipeStore;
use Domains\Bakery\Application\Repositories\OrderingRepositoryInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RecipeWebController
{
    public function __construct(private readonly OrderingRepositoryInterface $orders, private readonly ProductionCalculator $calculator, private readonly ProductionRecipeStore $store, private readonly IngredientCatalog $catalog) {}

    public function index(Request $request): View
    {
        $input = $request->validate([
            'section' => ['nullable', 'in:products,dough'],
            'product' => ['nullable', 'integer', 'exists:bakery_products,id'],
        ]);
        $section = $input['section'] ?? 'products';
        $products = $this->orders->products();
        $selected = $section === 'products'
            ? $products->firstWhere('id', (int) ($input['product'] ?? $products->first()?->id))
            : null;

        return view('bakery.recipes', [
            'section' => $section,
            'products' => $products,
            'selected' => $selected,
            'productRecipe' => $selected ? $this->calculator->productDefinition($selected->name, (int) $selected->id) : null,
            'mainDough' => $section === 'dough' ? $this->calculator->doughDefinition('main_dough') : null,
            'samsaDough' => $section === 'dough' ? $this->calculator->doughDefinition('samsa_dough') : null,
            'ingredientOptions' => $this->catalog->all(),
            'activeIngredientOptions' => $this->catalog->all()->filter(fn ($item): bool => (bool) $item->is_active)
                ->map(fn ($item): array => ['id' => (int) $item->id, 'name' => $item->name, 'unit' => $item->unit])->values()->all(),
        ]);
    }

    public function saveProduct(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'product' => ['required', 'integer', 'exists:bakery_products,id'],
            'dough_type' => ['nullable', 'in:main,samsa,none'],
            'dough_grams' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'ingredients' => ['sometimes', 'array', 'max:100'],
            'ingredients.*.ingredient_id' => ['nullable', 'integer'],
            'ingredients.*.name' => ['nullable', 'string', 'max:100'],
            'ingredients.*.amount' => ['required', 'numeric', 'gt:0', 'max:100000'],
            'ingredients.*.unit' => ['nullable', 'in:g,pcs,tbsp'],
        ]);
        $product = $this->orders->products()->firstWhere('id', (int) $data['product']);
        $doughType = $data['dough_type'] ?? $this->calculator->productDefinition($product->name, (int) $product->id)['dough_type'];
        if ($doughType === 'main' && (! isset($data['dough_grams']) || (float) $data['dough_grams'] <= 0)) {
            throw ValidationException::withMessages(['dough_grams' => 'Укажите вес основного теста на одно изделие.']);
        }
        $ingredients = $this->ingredients($data['ingredients'] ?? []);
        $this->store->save('product:'.$data['product'], [
            'dough_type' => $doughType,
            'dough_grams' => $doughType === 'main' ? (float) $data['dough_grams'] : null,
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
            'ingredients.*.ingredient_id' => ['nullable', 'integer'],
            'ingredients.*.name' => ['nullable', 'string', 'max:100'],
            'ingredients.*.amount' => ['required', 'numeric', 'gt:0', 'max:100000'],
        ]);
        $this->store->save($data['kind'], [
            'batch_size' => (float) $data['batch_size'],
            'ingredients' => $this->ingredients($data['ingredients'], 'g'),
        ]);

        return redirect()->route('bakery.recipes', ['section' => 'dough'])->with('success', 'Рецептура теста сохранена.');
    }

    public function reset(Request $request): RedirectResponse
    {
        $data = $request->validate(['kind' => ['required', 'in:product,main_dough,samsa_dough'], 'product' => ['required_if:kind,product', 'nullable', 'integer', 'exists:bakery_products,id']]);
        $key = $data['kind'] === 'product' ? 'product:'.$data['product'] : $data['kind'];
        $this->store->reset($key);

        $query = $data['kind'] === 'product' ? ['product' => $data['product']] : ['section' => 'dough'];

        return redirect()->route('bakery.recipes', $query)->with('success', 'Восстановлены исходные нормы из таблицы.');
    }

    /** @param array<int|string, array<string, mixed>> $rows
     * @return list<array{ingredient_id:int,name:string,amount:float,unit:string}>
     */
    private function ingredients(array $rows, ?string $requiredUnit = null): array
    {
        $result = [];
        $seen = [];
        $byId = $this->catalog->all()->keyBy('id');
        $byName = [];
        foreach ($byId as $ingredient) {
            $byName[$this->catalog->key($ingredient->name, $ingredient->unit)] = $ingredient;
            if ($ingredient->source_name !== null) {
                $byName[$this->catalog->key($ingredient->source_name, $ingredient->source_unit)] = $ingredient;
            }
        }
        foreach ($rows as $row) {
            $ingredient = isset($row['ingredient_id']) ? $byId->get((int) $row['ingredient_id']) : null;
            if (isset($row['ingredient_id']) && $ingredient === null) {
                throw ValidationException::withMessages(['ingredients' => 'Выберите ингредиент из справочника.']);
            }
            $unit = $requiredUnit ?? ($row['unit'] ?? '');
            $ingredient ??= $byName[$this->catalog->key((string) ($row['name'] ?? ''), $unit)] ?? null;
            if ($ingredient === null || ($requiredUnit !== null && $ingredient->unit !== $requiredUnit)) {
                throw ValidationException::withMessages(['ingredients' => 'Выберите ингредиент из справочника.']);
            }
            if (isset($seen[$ingredient->id])) {
                throw ValidationException::withMessages(['ingredients' => 'Один ингредиент указан дважды.']);
            }
            $seen[$ingredient->id] = true;
            $result[] = ['ingredient_id' => (int) $ingredient->id, 'name' => $ingredient->name, 'amount' => (float) $row['amount'], 'unit' => $ingredient->unit];
        }

        return $result;
    }
}
