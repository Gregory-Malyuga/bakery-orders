<?php

namespace Domains\Ingredient\Infrastructure\Repositories;

use Domains\Ingredient\Application\Repositories\IngredientRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class IngredientRepository implements IngredientRepositoryInterface
{
    public function all(): Collection
    {
        return DB::table('bakery_ingredients')->orderBy('name')->orderBy('unit')->get();
    }

    /** @return object{unit:string,is_active:bool}|null */
    public function find(int $id): ?object
    {
        /** @var object{unit:string,is_active:bool}|null $record */
        $record = DB::table('bakery_ingredients')->find($id);

        return $record;
    }

    public function existsWithName(string $name, string $unit, ?int $exceptId = null): bool
    {
        $query = DB::table('bakery_ingredients')
            ->where('unit', $unit)
            ->whereRaw('lower(name) = ?', [mb_strtolower($name)]);

        if ($exceptId !== null) {
            $query->where('id', '!=', $exceptId);
        }

        return $query->exists();
    }

    public function create(string $name, string $unit): void
    {
        DB::table('bakery_ingredients')->insert([
            'name' => $name, 'unit' => $unit, 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function rename(int $id, string $name): void
    {
        DB::table('bakery_ingredients')->where('id', $id)->update([
            'name' => $name, 'updated_at' => now(),
        ]);
    }

    public function setActive(int $id, bool $isActive): void
    {
        DB::table('bakery_ingredients')->where('id', $id)->update([
            'is_active' => $isActive, 'updated_at' => now(),
        ]);
    }
}
