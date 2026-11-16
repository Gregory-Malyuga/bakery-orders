<?php

namespace Domains\Recipe\Application\Repositories;

interface RecipeOverrideRepositoryInterface
{
    /** @param list<string> $keys
     * @return array<string, array<string, mixed>>
     */
    public function overrides(array $keys): array;

    /** @param array<string, mixed> $definition */
    public function save(string $key, array $definition): void;

    public function reset(string $key): void;
}
