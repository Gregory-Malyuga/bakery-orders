<?php

namespace Domains\OrderAdjustment\Application\Commands\Adjust;

use Domains\Order\Application\Repositories\OrderRepositoryInterface;
use Domains\OrderAdjustment\Application\Repositories\OrderAdjustmentRepositoryInterface;
use Domains\OrderItem\Domain\Models\OrderItem;
use Domains\Product\Application\PackagingPlanner;
use Illuminate\Validation\ValidationException;
use Shared\Bus\CommandHandlerInterface;

readonly class AdjustOrderHandler implements CommandHandlerInterface
{
    public function __construct(
        private OrderAdjustmentRepositoryInterface $repository,
        private OrderRepositoryInterface $orders,
        private PackagingPlanner $packaging,
    ) {}

    public function handle(object $message): ?int
    {
        assert($message instanceof AdjustOrderCommand);

        $this->repository->transaction(function () use ($message): void {
            $order = $this->repository->lockedOrder($message->orderId);
            abort_unless($order->status === 'accepted', 422, 'Отменённый заказ нельзя изменить.');
            $items = $this->repository->items($message->orderId)->keyBy('product_id');
            $before = $items->map(fn (OrderItem $item): array => $item->only([
                'product_id', 'product_name', 'quantity', 'unit_kopecks', 'total_kopecks',
            ]))->values()->all();
            $before = array_values($before);
            $prices = $this->orders->prices((int) $order->bakery_id);
            $products = $this->orders->products()->keyBy('id');
            $after = [];
            $total = 0;

            foreach ($message->quantities as $rawId => $rawQuantity) {
                if (! preg_match('/^[1-9][0-9]*$/', (string) $rawId)) {
                    throw ValidationException::withMessages(['quantities' => 'Некорректный товар в заявке.']);
                }
                $id = (int) $rawId;
                $quantity = (int) $rawQuantity;
                $item = $items->get($id);
                if ($quantity === 0) {
                    continue;
                }
                $product = $products->get($id);
                if ($item === null) {
                    if ($product === null || ! $product->is_active || ! isset($prices[$id])) {
                        throw ValidationException::withMessages(['quantities' => 'Новый товар недоступен или не имеет цены для магазина.']);
                    }
                    $unit = $prices[$id];
                    $name = $product->name;
                } else {
                    $unit = $item->unit_kopecks;
                    $name = $item->product_name;
                }
                if ($product !== null && $this->packaging->plan($quantity, $product->packaging_mode)['loose'] !== 0) {
                    throw ValidationException::withMessages(['quantities' => "Товар «{$name}» нельзя заказать в количестве {$quantity} шт. при выбранной фасовке."]);
                }
                $row = [
                    'product_id' => $id,
                    'product_name' => $name,
                    'quantity' => $quantity,
                    'unit_kopecks' => $unit,
                    'total_kopecks' => $quantity * $unit,
                ];
                $after[] = $row;
                $total += $row['total_kopecks'];
            }

            foreach ($items as $item) {
                if (! array_key_exists((int) $item->product_id, $message->quantities)) {
                    throw ValidationException::withMessages(['quantities' => 'Передайте количество для каждой позиции заказа.']);
                }
            }

            if ($after === []) {
                throw ValidationException::withMessages(['quantities' => 'В заказе должна остаться хотя бы одна позиция.']);
            }

            $this->repository->save(
                $message->orderId, $message->adminId, $before, $after, $total, $message->adminNote, $message->reason,
            );
        });

        return null;
    }
}
