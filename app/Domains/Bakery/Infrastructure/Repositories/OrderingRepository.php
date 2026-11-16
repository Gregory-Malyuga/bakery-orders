<?php

namespace Domains\Bakery\Infrastructure\Repositories;

use Domains\Bakery\Application\Repositories\OrderingRepositoryInterface;

/** @deprecated Use Domains\Order\Infrastructure\Repositories\OrderRepository. */
class OrderingRepository extends \Domains\Order\Infrastructure\Repositories\OrderRepository implements OrderingRepositoryInterface {}
