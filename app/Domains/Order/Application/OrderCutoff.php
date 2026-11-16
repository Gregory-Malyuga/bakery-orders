<?php

namespace Domains\Order\Application;

use Domains\Order\Application\Repositories\OrderCutoffRepositoryInterface;

readonly class OrderCutoff
{
    public function __construct(private OrderCutoffRepositoryInterface $repository) {}

    public function time(): string
    {
        return $this->repository->time();
    }

    public function isOpen(): bool
    {
        return now('Europe/Moscow')->format('H:i') < $this->time();
    }
}
