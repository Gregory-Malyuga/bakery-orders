<?php

namespace Domains\Bakery\Application;

use Domains\OrderReturn\Application\Repositories\OrderReturnReadRepositoryInterface;

/** @deprecated Use Domains\OrderReturn\Application\OrderReturnSummary. */
class OrderReturnSummary extends \Domains\OrderReturn\Application\OrderReturnSummary
{
    public function __construct(?OrderReturnReadRepositoryInterface $repository = null)
    {
        parent::__construct($repository ?? app(OrderReturnReadRepositoryInterface::class));
    }
}
