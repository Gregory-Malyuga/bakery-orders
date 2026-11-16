<?php

namespace Domains\OrderReturn\Application\Commands\Cancel;

use Domains\OrderReturn\Application\Repositories\OrderReturnRepositoryInterface;
use Shared\Bus\CommandHandlerInterface;

readonly class CancelOrderReturnHandler implements CommandHandlerInterface
{
    public function __construct(private OrderReturnRepositoryInterface $repository) {}

    public function handle(object $message): ?int
    {
        assert($message instanceof CancelOrderReturnCommand);

        $this->repository->transaction(function () use ($message): void {
            $order = $this->repository->lockedOrder($message->orderId);
            $record = $this->repository->lockedReturn($message->orderId, $message->returnId);
            abort_if($record === null, 404);
            abort_unless($order->status === 'accepted', 422, 'Возврат отменённого заказа нельзя изменить.');
            abort_unless($record->status === 'active', 422, 'Этот возврат уже отменён.');

            $this->repository->cancel($message->returnId, $message->adminId, $message->reason);
        });

        return null;
    }
}
