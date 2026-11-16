<?php

namespace Domains\Bakery\Application;

class ProductionCalculator
{
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
        'яйцо' => ['Майонез' => 7],
        'капуста' => ['Капуста' => 60, 'Соль' => 0.6, 'Специи' => 0.3],
        'картошка' => ['Картофельное пюре' => 60, 'Соль' => 0.36, 'Специи' => 0.42],
        'беляш' => ['Фарш' => 48, 'Лук' => 35, 'Соль' => 0.48, 'Специи' => 0.48],
        'яйцо рис' => ['Готовый рис' => 20, 'Лук' => 7, 'Майонез' => 10, 'Соль' => 1],
    ];

    /** @param array<string, int> $quantities
     * @return array{dough_grams:int,main_dough:array<string,float>,samsa_count:int,samsa_dough:array<string,float>,ingredients:array<string,float>,without_recipe:list<string>}
     */
    public function calculate(array $quantities): array
    {
        $doughGrams = 0;
        $samsaCount = 0;
        $ingredients = [];
        $withoutRecipe = [];
        foreach ($quantities as $name => $quantity) {
            $key = mb_strtolower(trim($name));
            $key = str_replace('ё', 'е', $key);
            $doughGrams += (self::DOUGH[$key] ?? 0) * $quantity;
            if (str_starts_with($key, 'самса')) {
                $samsaCount += $quantity;
            }
            if (! isset(self::FILLINGS[$key])) {
                $withoutRecipe[] = $name;

                continue;
            }
            foreach (self::FILLINGS[$key] as $ingredient => $grams) {
                $ingredients[$ingredient] = ($ingredients[$ingredient] ?? 0) + $grams * $quantity;
            }
        }
        ksort($ingredients);

        return [
            'dough_grams' => $doughGrams,
            'main_dough' => [
                'Вода' => 174 * $doughGrams / 1000,
                'Мука' => 564 * $doughGrams / 1000,
                'Масло' => 50 * $doughGrams / 1000,
                'Молоко' => 152 * $doughGrams / 1000,
                'Сахар' => 58 * $doughGrams / 1000,
                'Дрожжи' => 11 * $doughGrams / 1000,
                'Соль' => 6 * $doughGrams / 1000,
            ],
            'samsa_count' => $samsaCount,
            'samsa_dough' => [
                'Мука' => 3000 * $samsaCount / 44,
                'Вода' => 1500 * $samsaCount / 44,
                'Соль' => 60 * $samsaCount / 44,
                'Маргарин' => 600 * $samsaCount / 44,
            ],
            'ingredients' => $ingredients,
            'without_recipe' => $withoutRecipe,
        ];
    }
}
