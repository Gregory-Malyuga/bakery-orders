<?php

namespace Domains\Bakery\Domain\Database\Factories;

use Domains\Bakery\Domain\Models\Bakery;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Bakery>
 */
class BakeryFactory extends Factory
{
    protected $model = Bakery::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
        ];
    }
}
