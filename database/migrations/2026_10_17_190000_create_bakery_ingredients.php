<?php

use Domains\Bakery\Application\ProductionCalculator;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bakery_ingredients', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100);
            $table->string('unit', 8);
            $table->string('source_name', 100)->nullable();
            $table->string('source_unit', 8)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['name', 'unit']);
            $table->unique(['source_name', 'source_unit']);
        });

        $variants = ProductionCalculator::defaultIngredientVariants();
        foreach (DB::table('bakery_recipe_overrides')->pluck('definition') as $definition) {
            $recipe = json_decode($definition, true, 512, JSON_THROW_ON_ERROR);
            foreach ($recipe['ingredients'] ?? [] as $row) {
                if (isset($row['name'], $row['unit'])) {
                    $variants[] = ['name' => trim($row['name']), 'unit' => $row['unit']];
                }
            }
        }
        $seen = [];
        foreach ($variants as $variant) {
            $key = mb_strtolower($variant['name']).'|'.$variant['unit'];
            if ($variant['name'] === '' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            DB::table('bakery_ingredients')->insert([
                'name' => $variant['name'], 'unit' => $variant['unit'],
                'source_name' => $variant['name'], 'source_unit' => $variant['unit'],
                'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('bakery_ingredients');
    }
};
