<?php

namespace Domains\Bakery\Application;

use Domains\Bakery\Application\Repositories\OrderingRepositoryInterface;
use Domains\Bakery\Domain\Models\Order;
use Illuminate\Validation\ValidationException;

class OrderingService
{
    public function __construct(private readonly OrderingRepositoryInterface $repository, private readonly OrderCutoff $cutoff, private readonly PackagingPlanner $packaging, private readonly DeliveryCalendar $calendar) {}

    public function deliveryDate(): string
    {
        return $this->calendar->tomorrow();
    }

    public function isOpen(): bool
    {
        return $this->calendar->isOrderOpen();
    }

    /**
     * @param  array<int|string, int|string>  $quantities
     * @return array{date:string,items:list<array{product_id:int,product_name:string,quantity:int,unit_kopecks:int,total_kopecks:int}>,total:int,hash:string}
     */
    public function quote(int $storeId, array $quantities): array
    {
        $date = $this->deliveryDate();
        if ($this->calendar->isClosed($date)) {
            throw ValidationException::withMessages(['order' => 'Поставка на '.now('Europe/Moscow')->addDay()->format('d.m.Y').' закрыта.']);
        }
        $prices = $this->repository->prices($storeId);
        $minimums = $this->repository->minimums($storeId);
        $products = $this->repository->products();
        $items = [];
        foreach ($quantities as $id => $rawQuantity) {
            if (! preg_match('/^(0|[1-9][0-9]*)$/', (string) $rawQuantity) || (int) $rawQuantity > 100000) {
                throw ValidationException::withMessages(['quantities' => 'Количество должно быть целым числом от 0 до 100000.']);
            }
            $quantity = (int) $rawQuantity;
            if ($quantity === 0) {
                continue;
            }
            $product = $products->firstWhere('id', (int) $id);
            if ($product === null || ! $product->is_active || ! isset($prices[(int) $id])) {
                throw ValidationException::withMessages(['quantities' => 'Один из товаров недоступен или не имеет цены. Обновите форму.']);
            }
            $minimum = $minimums[$product->id] ?? $product->minimum_quantity;
            if ($quantity < $minimum) {
                throw ValidationException::withMessages(['quantities' => "Минимальный заказ товара «{$product->name}» — {$minimum} шт."]);
            }
            if ($this->packaging->plan($quantity, $product->packaging_mode)['loose'] !== 0) {
                $rule = $product->packaging_mode === 'pair' ? 'по 2 шт.' : 'по 3 и 5 шт.';
                throw ValidationException::withMessages(['quantities' => "Товар «{$product->name}» фасуется {$rule}: количество {$quantity} заказать нельзя."]);
            }
            $unit = $prices[(int) $id];
            $items[] = ['product_id' => (int) $id, 'product_name' => $product->name, 'quantity' => $quantity, 'unit_kopecks' => $unit, 'total_kopecks' => $quantity * $unit];
        }
        if ($items === []) {
            throw ValidationException::withMessages(['quantities' => 'Выберите хотя бы один товар.']);
        }
        usort($items, fn (array $a, array $b): int => $a['product_id'] <=> $b['product_id']);
        $total = array_sum(array_column($items, 'total_kopecks'));

        return ['date' => $date, 'items' => $items, 'total' => $total, 'hash' => hash('sha256', json_encode([$date, $items], JSON_THROW_ON_ERROR))];
    }

    /** @param array<int|string, int|string> $quantities
     * @return array{order:Order|null,quote:array{date:string,items:list<array{product_id:int,product_name:string,quantity:int,unit_kopecks:int,total_kopecks:int}>,total:int,hash:string}|null}
     */
    public function place(int $storeId, string $key, array $quantities, string $expectedHash, ?string $comment = null): array
    {
        $previous = $this->repository->request($storeId, $key);
        if ($previous !== null) {
            return ['order' => $previous, 'quote' => null];
        }

        $result = $this->repository->lockedStore($storeId, function () use ($storeId, $key, $quantities, $expectedHash, $comment): array {
            $previous = $this->repository->request($storeId, $key);
            if ($previous !== null) {
                return ['order' => $previous, 'quote' => null];
            }
            if ($this->calendar->isClosed($this->deliveryDate())) {
                throw ValidationException::withMessages(['order' => 'Поставка на завтра закрыта. Заказ не принимается.']);
            }
            if (! $this->isOpen()) {
                throw ValidationException::withMessages(['order' => 'Приём заказов на завтра закрыт в '.$this->cutoff->time().' МСК.']);
            }
            $store = $this->repository->store($storeId);
            if ($store === null || ! $store->is_active) {
                abort(403);
            }
            $existing = $this->repository->activeOrder($storeId, $this->deliveryDate());
            if ($existing !== null) {
                return ['order' => $existing, 'quote' => null];
            }
            $quote = $this->quote($storeId, $quantities);
            if (! hash_equals($quote['hash'], $expectedHash)) {
                return ['order' => null, 'quote' => $quote];
            }

            return ['order' => $this->repository->createOrder($store, $quote['date'], $key, $quote['items'], $quote['total'], $comment), 'quote' => null];
        });
        /** @var array{order:Order|null,quote:array{date:string,items:list<array{product_id:int,product_name:string,quantity:int,unit_kopecks:int,total_kopecks:int}>,total:int,hash:string}|null} $result */

        return $result;
    }

    public function cancel(int $storeId, int $orderId): void
    {
        $this->repository->lockedStore($storeId, function () use ($storeId, $orderId): Order {
            $order = $this->repository->order($orderId);
            if ($order === null || $order->bakery_id !== $storeId) {
                abort(404);
            }
            if ($order->status !== 'accepted') {
                return $order;
            }
            if (! $this->cutoff->isOpen() || $order->created_at?->timezone('Europe/Moscow')->toDateString() !== now('Europe/Moscow')->toDateString()) {
                throw ValidationException::withMessages(['order' => 'Отмена доступна только до '.$this->cutoff->time().' МСК дня оформления.']);
            }
            $this->repository->cancel($order);

            return $order;
        });
    }
}
