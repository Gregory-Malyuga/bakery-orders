<?php

namespace Domains\Recipe\Application;

use Domains\Ingredient\Application\IngredientCatalog;
use Domains\Order\Application\Repositories\OrderRepositoryInterface;
use Domains\Production\Application\ProductionCalculator;
use Illuminate\Validation\ValidationException;

readonly class RecipeEditorService
{
    public function __construct(
        private OrderRepositoryInterface $orders,
        private ProductionCalculator $calculator,
        private ProductionRecipeStore $store,
        private IngredientCatalog $catalog,
    ) {}

    /** @return array<string, mixed> */
    public function editor(string $section, ?int $productId): array
    {
        $products = $this->orders->products();
        $selectedProductId = $productId ?? $products->first()?->id;
        $selected =
            $section === 'products' && is_numeric($selectedProductId)
                ? $products->firstWhere('id', (int) $selectedProductId)
                : null;

        return [
            'section' => $section,
            'products' => $products,
            'selected' => $selected,
            'productRecipe' => $selected
                ? $this->calculator->productDefinition($selected->name, (int) $selected->id)
                : null,
            'mainDough' => $section === 'dough' ? $this->calculator->doughDefinition('main_dough') : null,
            'samsaDough' => $section === 'dough' ? $this->calculator->doughDefinition('samsa_dough') : null,
            'ingredientOptions' => $this->catalog->all(),
            'activeIngredientOptions' => $this->catalog
                ->all()
                ->filter(fn ($item): bool => (bool) $item->is_active)
                ->map(function ($item): array {
                    /** @var int $id */
                    $id = $item->id;

                    return ['id' => $id, 'name' => $item->name, 'unit' => $item->unit];
                })
                ->values()
                ->all(),
        ];
    }

    /**
     * @param array{product:int|string,dough_type?:string,dough_grams?:int|float|numeric-string,
     *     ingredients?:list<array<string,mixed>>} $data
     */
    public function saveProduct(array $data): void
    {
        $product = $this->orders->products()->firstWhere('id', (int) $data['product']);
        if ($product === null) {
            abort(404);
        }
        $doughType =
            $data['dough_type'] ??
            $this->calculator->productDefinition($product->name, (int) $product->id)['dough_type'];
        if (
            $doughType === 'main' &&
            (! isset($data['dough_grams']) || (float) $data['dough_grams'] <= 0)
        ) {
            throw ValidationException::withMessages([
                'dough_grams' => 'Укажите вес основного теста на одно изделие.',
            ]);
        }
        $ingredients = $this->ingredients($data['ingredients'] ?? []);
        $this->store->save('product:'.$data['product'], [
            'dough_type' => $doughType,
            'dough_grams' => $doughType === 'main' ? (float) $data['dough_grams'] : null,
            'ingredients' => $ingredients,
        ]);
    }

    /** @param array{kind:string,batch_size:int|float|numeric-string,ingredients:list<array<string,mixed>>} $data */
    public function saveDough(array $data): void
    {
        $this->store->save($data['kind'], [
            'batch_size' => (float) $data['batch_size'],
            'ingredients' => $this->ingredients($data['ingredients'], 'g'),
        ]);
    }

    /** @param array{kind:string,product?:int|string} $data */
    public function reset(array $data): void
    {
        $productId = $data['product'] ?? null;
        if ($data['kind'] === 'product' && $productId === null) {
            abort(422);
        }
        $key = $data['kind'] === 'product' ? 'product:'.$productId : $data['kind'];
        $this->store->reset($key);
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
            /** @var object{id:int,name:string,unit:string,source_name:?string,source_unit:?string} $ingredient */
            $byName[$this->catalog->key($ingredient->name, $ingredient->unit)] = $ingredient;
            if ($ingredient->source_name !== null) {
                $byName[
                    $this->catalog->key($ingredient->source_name, $ingredient->source_unit ?? '')
                ] = $ingredient;
            }
        }
        foreach ($rows as $row) {
            $ingredientId = $row['ingredient_id'] ?? null;
            $ingredient = is_numeric($ingredientId) ? $byId->get((int) $ingredientId) : null;
            if (isset($row['ingredient_id']) && $ingredient === null) {
                throw ValidationException::withMessages([
                    'ingredients' => 'Выберите ингредиент из справочника.',
                ]);
            }
            $unit = $row['unit'] ?? '';
            $name = $row['name'] ?? '';
            $ingredient ??=
                $byName[
                    $this->catalog->key(
                        is_string($name) ? $name : '',
                        $requiredUnit ?? (is_string($unit) ? $unit : ''),
                    )
                ] ?? null;
            if (
                $ingredient === null ||
                ($requiredUnit !== null && $ingredient->unit !== $requiredUnit)
            ) {
                throw ValidationException::withMessages([
                    'ingredients' => 'Выберите ингредиент из справочника.',
                ]);
            }
            /** @var object{id:int,name:string,unit:string} $ingredient */
            if (isset($seen[$ingredient->id])) {
                throw ValidationException::withMessages([
                    'ingredients' => 'Один ингредиент указан дважды.',
                ]);
            }
            $seen[$ingredient->id] = true;
            $amount = $row['amount'] ?? null;
            if (! is_numeric($amount)) {
                throw ValidationException::withMessages([
                    'ingredients' => 'Укажите количество ингредиента.',
                ]);
            }
            $result[] = [
                'ingredient_id' => $ingredient->id,
                'name' => $ingredient->name,
                'amount' => (float) $amount,
                'unit' => $ingredient->unit,
            ];
        }

        return $result;
    }
}
