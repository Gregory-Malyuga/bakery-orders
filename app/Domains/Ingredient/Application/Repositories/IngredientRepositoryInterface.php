<?php

namespace Domains\Ingredient\Application\Repositories;

use Illuminate\Support\Collection;

interface IngredientRepositoryInterface
{
    /** @return Collection<int, \stdClass> */
    public function all(): Collection;

    /** @return object{unit:string,is_active:bool}|null */
    public function find(int $id): ?object;

    public function existsWithName(string $name, string $unit, ?int $exceptId = null): bool;

    public function create(string $name, string $unit): void;

    public function rename(int $id, string $name): void;

    public function setActive(int $id, bool $isActive): void;
}
