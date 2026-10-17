<?php

namespace Domains\Bakery\Application;

class ProductionCalculator
{
    public function __construct(private readonly ?ProductionRecipeStore $store = null) {}

    /** @var array<string, int> Grams of main dough per item, from sheet 12.09. */
    private const DOUGH = [
        'сосиска' => 62, 'капуста' => 68, 'картошка' => 62, 'курица' => 62,
        'курица лук' => 62, 'яйцо' => 62, 'яйцо рис' => 62, 'ветчина' => 62,
        'беляш' => 90, 'пицца' => 136, 'мак' => 150, 'вишня' => 62,
        'изюм' => 150, 'яблоко' => 62, 'косичка' => 68, 'косичка с маком' => 68,
        'ватрушка' => 100, 'курник' => 68, 'лимон' => 62, 'малина' => 62,
        'тропикано' => 62,
    ];

    /** @var array<string, array<string, int|float>> Grams per item, from the worksheet's prep formulas. */
    private const FILLINGS = [
        'пицца' => ['Помидоры' => 40, 'Колбаса' => 30, 'Сыр' => 19, 'Огурцы' => 7, 'Майонез' => 4, 'Томатная паста' => 4],
        'ветчина' => ['Ветчина' => 35, 'Майонез' => 10, 'Сыр' => 12],
        'ватрушка' => ['Творог' => 35.64, 'Сахар' => 4.5, 'Яйцо, г' => 3.6, 'Масло' => 1.8, 'Мука' => 1.8],
        'курник' => ['Картофель' => 20, 'Курица' => 20, 'Лук' => 20],
        'хачапури' => ['Творог' => 15, 'Гауда' => 30, 'Сулугуни' => 5, 'Яйцо, г' => 5, 'Соль' => 0.5],
        'самса' => ['Курица' => 45, 'Лук' => 35],
        'самса курица' => ['Курица' => 45, 'Лук' => 35],
        'самса с говядиной' => ['Говядина' => 42, 'Лук' => 35],
        'самса говядина' => ['Говядина' => 42, 'Лук' => 35],
        'курица' => ['Куриная грудка' => 32],
        'курица лук' => ['Куриная грудка' => 32],
        'яйцо' => ['Майонез' => 7, 'Лук' => 12],
        'капуста' => ['Капуста' => 60, 'Соль' => 0.6, 'Специи' => 0.3],
        'картошка' => ['Картофельное пюре' => 60, 'Соль' => 0.36, 'Специи' => 0.42],
        'беляш' => ['Фарш' => 48, 'Лук' => 35, 'Соль' => 0.48, 'Специи' => 0.48],
        'яйцо рис' => ['Готовый рис' => 20, 'Лук' => 7, 'Майонез' => 10, 'Соль' => 1],
    ];

    /** Quantities whose source units are not grams. */
    private const OTHER_INGREDIENTS = [
        'капуста' => ['Яйцо' => ['quantity' => 1 / 50, 'unit' => 'шт.']],
        'яйцо' => ['Яйцо' => ['quantity' => 0.9, 'unit' => 'шт.']],
        'самса' => ['Специи' => ['quantity' => 0.045, 'unit' => 'ст. л.']],
        'самса курица' => ['Специи' => ['quantity' => 0.045, 'unit' => 'ст. л.']],
        'самса с говядиной' => ['Специи' => ['quantity' => 0.042, 'unit' => 'ст. л.']],
        'самса говядина' => ['Специи' => ['quantity' => 0.042, 'unit' => 'ст. л.']],
    ];

    private const MAIN_DOUGH = ['batch_size' => 1000, 'ingredients' => ['Вода' => 174, 'Мука' => 564, 'Масло' => 50, 'Молоко' => 152, 'Сахар' => 58, 'Дрожжи' => 11, 'Соль' => 6]];

    private const SAMSA_DOUGH = ['batch_size' => 44, 'ingredients' => ['Мука' => 3000, 'Вода' => 1500, 'Соль' => 60, 'Маргарин' => 600]];

    /** @return array{dough_grams:float|null,ingredients:list<array{name:string,amount:float,unit:string}>} */
    public function productDefinition(string $name, ?int $productId = null): array
    {
        $key = $this->normalize($name);
        $ingredients = [];
        foreach (self::FILLINGS[$key] ?? [] as $ingredient => $grams) {
            $ingredients[] = ['name' => $ingredient, 'amount' => $grams, 'unit' => 'g'];
        }
        foreach (self::OTHER_INGREDIENTS[$key] ?? [] as $ingredient => $measure) {
            $unit = $measure['unit'] === 'шт.' ? 'pcs' : 'tbsp';
            $ingredients[] = ['name' => $ingredient, 'amount' => (float) $measure['quantity'], 'unit' => $unit];
        }
        $default = ['dough_grams' => self::DOUGH[$key] ?? null, 'ingredients' => $ingredients];
        if ($productId === null || $this->store === null) {
            return $default;
        }

        /** @var array{dough_grams:float|null,ingredients:list<array{name:string,amount:float,unit:string}>}|null $override */
        $override = $this->store->overrides(['product:'.$productId])['product:'.$productId] ?? null;

        return $override ?? $default;
    }

    /** @return array{batch_size:float,ingredients:list<array{name:string,amount:float,unit:string}>} */
    public function doughDefinition(string $kind): array
    {
        $default = $kind === 'samsa_dough' ? self::SAMSA_DOUGH : self::MAIN_DOUGH;
        $ingredients = [];
        foreach ($default['ingredients'] as $name => $grams) {
            $ingredients[] = ['name' => $name, 'amount' => $grams, 'unit' => 'g'];
        }

        /** @var array{batch_size:float,ingredients:list<array{name:string,amount:float,unit:string}>}|null $override */
        $override = $this->store?->overrides([$kind])[$kind] ?? null;

        return $override ?? ['batch_size' => $default['batch_size'], 'ingredients' => $ingredients];
    }

    private function normalize(string $name): string
    {
        return str_replace('ё', 'е', mb_strtolower(trim($name)));
    }

    /** @param array<string, int> $quantities
     * @param  array<string, int>  $productIdsByName
     * @return array{dough_grams:int|float,main_dough:array<string,float>,samsa_count:int,samsa_batch_size:int|float,samsa_dough:array<string,float>,ingredients:array<string,int|float>,other_ingredients:array<string,array<string,int|float>>,recipes:array<string, array{quantity:int,ingredients:array<string,int|float>,other_ingredients:array<string,array{quantity:int|float,unit:string}>}>,without_recipe:list<string>}
     */
    public function calculate(array $quantities, array $productIdsByName = []): array
    {
        $doughGrams = 0;
        $samsaCount = 0;
        /** @var array<string, int|float> $ingredients */
        $ingredients = [];
        /** @var array<string, array<string, int|float>> $otherIngredients */
        $otherIngredients = [];
        /** @var array<string, array{quantity:int,ingredients:array<string,int|float>,other_ingredients:array<string,array{quantity:int|float,unit:string}>}> $recipes */
        $recipes = [];
        $withoutRecipe = [];
        $keys = ['main_dough', 'samsa_dough'];
        foreach ($productIdsByName as $id) {
            $keys[] = 'product:'.$id;
        }
        $overrides = $this->store?->overrides($keys) ?? [];
        foreach ($quantities as $name => $quantity) {
            $key = $this->normalize($name);
            $productKey = isset($productIdsByName[$name]) ? 'product:'.$productIdsByName[$name] : null;
            $definition = $productKey !== null && isset($overrides[$productKey])
                ? $overrides[$productKey]
                : $this->productDefinition($name);
            $doughGrams += ($definition['dough_grams'] ?? 0) * $quantity;
            if (str_starts_with($key, 'самса')) {
                $samsaCount += $quantity;
            }
            if ($definition['ingredients'] === []) {
                $withoutRecipe[] = $name;

                continue;
            }
            $recipeIngredients = [];
            $recipeOtherIngredients = [];
            foreach ($definition['ingredients'] as $ingredient) {
                $amount = $ingredient['amount'] * $quantity;
                if ($ingredient['unit'] === 'g') {
                    $ingredients[$ingredient['name']] = ($ingredients[$ingredient['name']] ?? 0) + $amount;
                    $recipeIngredients[$ingredient['name']] = ($recipeIngredients[$ingredient['name']] ?? 0) + $amount;
                } else {
                    $unit = $ingredient['unit'] === 'pcs' ? 'шт.' : 'ст. л.';
                    $recipeOtherIngredients[$ingredient['name']] = ['quantity' => $amount, 'unit' => $unit];
                    $otherIngredients[$unit][$ingredient['name']] = ($otherIngredients[$unit][$ingredient['name']] ?? 0) + $amount;
                }
            }
            $recipes[$name] = ['quantity' => $quantity, 'ingredients' => $recipeIngredients, 'other_ingredients' => $recipeOtherIngredients];
        }
        ksort($ingredients);
        $mainDough = $overrides['main_dough'] ?? $this->doughDefinition('main_dough');
        $samsaDough = $overrides['samsa_dough'] ?? $this->doughDefinition('samsa_dough');
        $mainIngredients = [];
        foreach ($mainDough['ingredients'] as $ingredient) {
            $mainIngredients[$ingredient['name']] = $ingredient['amount'] * $doughGrams / $mainDough['batch_size'];
        }
        $samsaIngredients = [];
        foreach ($samsaDough['ingredients'] as $ingredient) {
            $samsaIngredients[$ingredient['name']] = $ingredient['amount'] * $samsaCount / $samsaDough['batch_size'];
        }

        return [
            'dough_grams' => $doughGrams,
            'main_dough' => $mainIngredients,
            'samsa_count' => $samsaCount,
            'samsa_batch_size' => $samsaDough['batch_size'],
            'samsa_dough' => $samsaIngredients,
            'ingredients' => $ingredients,
            'other_ingredients' => $otherIngredients,
            'recipes' => $recipes,
            'without_recipe' => $withoutRecipe,
        ];
    }
}
