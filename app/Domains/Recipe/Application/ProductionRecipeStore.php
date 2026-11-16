<?php

namespace Domains\Recipe\Application;

use Domains\Recipe\Application\Repositories\RecipeOverrideRepositoryInterface;

class ProductionRecipeStore
{
    public function __construct(private readonly RecipeOverrideRepositoryInterface $repository) {}

    /** @param list<string> $keys
     * @return array<string, array<string, mixed>>
     */
    public function overrides(array $keys): array
    {
        return $this->repository->overrides($keys);
    }

    /** @param array<string, mixed> $definition */
    public function save(string $key, array $definition): void
    {
        $this->repository->save($key, $definition);
    }

    public function reset(string $key): void
    {
        $this->repository->reset($key);
    }
}
