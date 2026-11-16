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

        /** @var array<string, array<string, mixed>> $overrides */
        $overrides = [];
        foreach (DB::table('bakery_recipe_overrides')->whereIn('recipe_key', $keys)->get(['recipe_key', 'definition']) as $row) {
            if (! is_string($row->recipe_key) || ! is_string($row->definition)) {
                continue;
            }
            $definition = json_decode($row->definition, true, 512, JSON_THROW_ON_ERROR);
            if (is_array($definition)) {
                /** @var array<string, mixed> $definition */
                $overrides[$row->recipe_key] = $definition;
            }
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
