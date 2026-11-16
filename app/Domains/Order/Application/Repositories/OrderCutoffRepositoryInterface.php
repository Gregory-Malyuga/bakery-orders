<?php

namespace Domains\Order\Application\Repositories;

interface OrderCutoffRepositoryInterface
{
    public function time(): string;

    public function save(string $value): void;
}
