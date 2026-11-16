<?php

namespace Domains\Bakery\Application;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class IngredientCatalog
{
    /** @return Collection<int, \stdClass> */
    public function all(): Collection
    {
        return DB::table('bakery_ingredients')->orderBy('name')->orderBy('unit')->get();
    }

    /**
     * @param  list<array{name:string,amount:float,unit:string,ingredient_id?:int}>  $rows
     * @return list<array{name:string,amount:float,unit:string,ingredient_id?:int}>
     */
    public function resolveRows(array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        $byId = [];
        $bySource = [];
        $byName = [];
        foreach ($this->all() as $ingredient) {
            /** @var object{id:int,name:string,unit:string,source_name:?string,source_unit:?string} $ingredient */
            $byId[$ingredient->id] = $ingredient;
            if ($ingredient->source_name !== null) {
                $bySource[$this->key($ingredient->source_name, $ingredient->source_unit ?? '')] = $ingredient;
            }
            $byName[$this->key($ingredient->name, $ingredient->unit)] = $ingredient;
        }

        foreach ($rows as &$row) {
            $id = $row['ingredient_id'] ?? null;
            $key = $this->key($row['name'], $row['unit']);
            $ingredient = $id !== null ? ($byId[$id] ?? null) : null;
            $ingredient ??= $bySource[$key] ?? null;
            $ingredient ??= $byName[$key] ?? null;
            if ($ingredient !== null) {
                $row['ingredient_id'] = (int) $ingredient->id;
                $row['name'] = $ingredient->name;
                $row['unit'] = $ingredient->unit;
            }
        }
        unset($row);

        return $rows;
    }

    public function key(string $name, string $unit): string
    {
        return mb_strtolower(trim($name)).'|'.$unit;
    }
}
