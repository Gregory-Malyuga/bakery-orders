<?php

namespace Domains\Bakery\Application;

use Illuminate\Support\Facades\DB;

class ProductionRecipeStore
{
    /** @param list<string> $keys
     * @return array<string, array<string, mixed>>
     */
    public function overrides(array $keys): array
    {
        if ($keys === []) {
            return [];
        }

        $overrides = [];
        foreach (DB::table('bakery_recipe_overrides')->whereIn('recipe_key', $keys)->get(['recipe_key', 'definition']) as $row) {
            $overrides[$row->recipe_key] = json_decode($row->definition, true, 512, JSON_THROW_ON_ERROR);
        }

        return $overrides;
    }

    /** @param array<string, mixed> $definition */
    public function save(string $key, array $definition): void
    {
        DB::table('bakery_recipe_overrides')->upsert(
            [['recipe_key' => $key, 'definition' => json_encode($definition, JSON_THROW_ON_ERROR), 'created_at' => now(), 'updated_at' => now()]],
            ['recipe_key'],
            ['definition', 'updated_at'],
        );
    }

    public function reset(string $key): void
    {
        DB::table('bakery_recipe_overrides')->where('recipe_key', $key)->delete();
    }
}
