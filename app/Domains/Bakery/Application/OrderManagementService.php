<?php

namespace Domains\Bakery\Application;

use Domains\OrderAdjustment\Application\Commands\Adjust\AdjustOrderCommand;
use Domains\OrderAdjustment\Application\Commands\Adjust\AdjustOrderHandler;
use Domains\OrderReturn\Application\Commands\Cancel\CancelOrderReturnCommand;
use Domains\OrderReturn\Application\Commands\Cancel\CancelOrderReturnHandler;
use Domains\OrderReturn\Application\Commands\Record\RecordOrderReturnCommand;
use Domains\OrderReturn\Application\Commands\Record\RecordOrderReturnHandler;

/** @deprecated Use the OrderAdjustment and OrderReturn command handlers directly. */
readonly class OrderManagementService
{
    public function __construct(
        private AdjustOrderHandler $adjustHandler,
        private RecordOrderReturnHandler $recordHandler,
        private CancelOrderReturnHandler $cancelHandler,
    ) {}

    /** @param array<int|string, int|string> $quantities */
    public function adjust(int $orderId, int $adminId, array $quantities, string $reason, ?string $adminNote): void
    {
        $this->adjustHandler->handle(new AdjustOrderCommand($orderId, $adminId, $quantities, $reason, $adminNote));
    }

    public function recordReturn(int $orderId, int $adminId, int $productId, int $quantity, ?string $reason): void
    {
        $this->recordHandler->handle(new RecordOrderReturnCommand($orderId, $adminId, $productId, $quantity, $reason));
    }

    public function cancelReturn(int $orderId, int $returnId, int $adminId, string $reason): void
    {
        $this->cancelHandler->handle(new CancelOrderReturnCommand($orderId, $returnId, $adminId, $reason));
    }
}
