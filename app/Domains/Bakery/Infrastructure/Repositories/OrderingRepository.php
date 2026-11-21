<?php

namespace Domains\Bakery\Infrastructure\Repositories;

use Domains\Bakery\Application\Repositories\OrderingRepositoryInterface;
use Domains\Order\Infrastructure\Repositories\OrderRepository;

/** @deprecated Use Domains\Order\Infrastructure\Repositories\OrderRepository. */
class OrderingRepository extends OrderRepository implements OrderingRepositoryInterface {}
