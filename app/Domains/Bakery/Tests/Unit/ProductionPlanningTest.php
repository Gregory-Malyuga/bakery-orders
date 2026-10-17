<?php

namespace Domains\Bakery\Tests\Unit;

use Domains\Bakery\Application\PackagingPlanner;
use Domains\Bakery\Application\ProductionCalculator;
use PHPUnit\Framework\TestCase;

class ProductionPlanningTest extends TestCase
{
    public function test_packaging_keeps_store_orders_separate_and_flags_unpacked_pieces(): void
    {
        $planner = new PackagingPlanner;

        $this->assertSame(['one' => 0, 'two' => 0, 'three' => 1, 'five' => 1, 'loose' => 0], $planner->plan(8, 'mixed'));
        $this->assertSame(2, $planner->plan(3, 'mixed')['three'] + $planner->plan(3, 'mixed')['three']);
        $this->assertSame(1, $planner->plan(7, 'mixed')['loose']);
        $this->assertSame(2, $planner->plan(5, 'pair')['two']);
        $this->assertSame(1, $planner->plan(5, 'pair')['loose']);
        $this->assertSame(5, $planner->plan(5, 'single')['one']);
    }

    public function test_production_uses_reference_piece_weights_and_reports_missing_recipes(): void
    {
        $result = (new ProductionCalculator)->calculate(['Пицца' => 2, 'Самса' => 44, 'Вишня' => 3]);

        $this->assertSame(458, $result['dough_grams']);
        $this->assertEqualsWithDelta(258.312, $result['main_dough']['Мука'], 0.0001);
        $this->assertSame(44, $result['samsa_count']);
        $this->assertEqualsWithDelta(3000, $result['samsa_dough']['Мука'], 0.0001);
        $this->assertSame(80, $result['ingredients']['Помидоры']);
        $this->assertSame(2, $result['recipes']['Пицца']['quantity']);
        $this->assertSame(80, $result['recipes']['Пицца']['ingredients']['Помидоры']);
        $this->assertSame(1980, $result['recipes']['Самса']['ingredients']['Курица']);
        $this->assertSame(1980, $result['ingredients']['Курица']);
        $this->assertSame(['Вишня'], $result['without_recipe']);
    }

    public function test_prep_includes_eggs_on_cabbage_and_egg_pies_and_spices_on_samsa(): void
    {
        $result = (new ProductionCalculator)->calculate(['Капуста' => 50, 'Яйцо' => 10, 'Самса' => 20, 'Самса с говядиной' => 10]);

        $this->assertSame(120, $result['recipes']['Яйцо']['ingredients']['Лук']);
        $this->assertEqualsWithDelta(1, $result['recipes']['Капуста']['other_ingredients']['Яйцо']['quantity'], 0.0001);
        $this->assertEqualsWithDelta(9, $result['recipes']['Яйцо']['other_ingredients']['Яйцо']['quantity'], 0.0001);
        $this->assertEqualsWithDelta(0.9, $result['recipes']['Самса']['other_ingredients']['Специи']['quantity'], 0.0001);
        $this->assertEqualsWithDelta(0.42, $result['recipes']['Самса с говядиной']['other_ingredients']['Специи']['quantity'], 0.0001);
    }
}
