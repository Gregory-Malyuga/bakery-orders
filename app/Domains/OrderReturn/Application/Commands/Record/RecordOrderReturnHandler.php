<?php

namespace Domains\OrderReturn\Application\Commands\Record;

use Domains\Order\Application\Repositories\OrderRepositoryInterface;
use Domains\OrderReturn\Application\Repositories\OrderReturnRepositoryInterface;
use Illuminate\Validation\ValidationException;
use Shared\Bus\CommandHandlerInterface;

readonly class RecordOrderReturnHandler implements CommandHandlerInterface
{
    public function __construct(
        private OrderReturnRepositoryInterface $repository,
        private OrderRepositoryInterface $orders,
    ) {}

    public function handle(object $message): ?int
    {
        assert($message instanceof RecordOrderReturnCommand);

        $this->repository->transaction(function () use ($message): void {
            $order = $this->repository->lockedOrder($message->orderId);
            abort_unless(
                $order->status === 'accepted',
                422,
                'Возврат можно зафиксировать только для принятого заказа.',
            );

            $item = $this->repository->item($message->orderId, $message->productId);
            if ($item === null) {
                $product = $this->orders->products()->firstWhere('id', $message->productId);
                $prices = $this->orders->prices((int) $order->bakery_id);
                if (
                    $product === null ||
                    ! $product->is_active ||
                    ! isset($prices[$message->productId])
                ) {
                    throw ValidationException::withMessages([
                        'product' => 'Товар недоступен для заказа этому магазину.',
                    ]);
                }
                $name = $product->name;
                $unit = $prices[$message->productId];
            } else {
                $name = $item->product_name;
                $unit = $item->unit_kopecks;
            }

            $this->repository->record(
                $message->orderId,
                $message->adminId,
                $message->productId,
                $name,
                $unit,
                $message->quantity,
                $item !== null,
                $message->reason,
            );
        });

        return null;
    }
}
