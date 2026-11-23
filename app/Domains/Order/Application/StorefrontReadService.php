<?php

namespace Domains\Order\Application;

use Domains\DeliverySchedule\Application\DeliveryCalendar;
use Domains\Order\Application\Repositories\OrderRepositoryInterface;
use Domains\Order\Domain\Models\Order;
use Domains\Product\Domain\Models\Product;
use Domains\Store\Domain\Models\Store;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

readonly class StorefrontReadService
{
    public function __construct(
        private OrderingService $service,
        private OrderRepositoryInterface $repository,
        private OrderCutoff $cutoff,
        private DeliveryCalendar $calendar,
    ) {}

    /**
     * @param  list<int>  $accessible
     * @return array{store:Store,accessibleStores:Collection<int,Store>,date:string,open:bool,
     *     closedReason:string|null,nextWindow:array{order_date:string,delivery_date:string},
     *     orderCutoff:string,products:Collection<int,Product>,prices:array<int,int>,minimums:array<int,
     *     int>,activeOrder:Order|null,history:Collection<int,Order>,requestKey:string,
     *     repeatQuantities:array<int,int>}
     */
    public function home(int $storeId, array $accessible): array
    {
        abort_unless(in_array($storeId, $accessible, true), 403);
        $store = $this->repository->store($storeId);
        abort_unless($store !== null && $store->is_active, 403);

        return [
            'store' => $store,
            'accessibleStores' => $this->repository
                ->stores()
                ->whereIn('id', $accessible)
                ->where('is_active', true),
            'date' => $this->service->deliveryDate(),
            'open' => $this->service->isOpen(),
            'closedReason' => $this->calendar->reason($this->service->deliveryDate()),
            'nextWindow' => $this->calendar->nextWindow(),
            'orderCutoff' => $this->cutoff->time(),
            'products' => $this->repository->products()->where('is_active', true),
            'prices' => $this->repository->prices($storeId),
            'minimums' => $this->repository->minimums($storeId),
            'activeOrder' => $this->repository->activeOrder(
                $storeId,
                $this->service->deliveryDate(),
            ),
            'history' => $this->repository->history($storeId),
            'requestKey' => (string) Str::uuid(),
            'repeatQuantities' => [],
        ];
    }

    /**
     * @param  list<int>  $accessible
     * @return array{store:Store,accessibleStores:Collection<int,Store>,date:string,open:bool,
     *     closedReason:string|null,nextWindow:array{order_date:string,delivery_date:string},
     *     orderCutoff:string,products:Collection<int,Product>,prices:array<int,int>,minimums:array<int,
     *     int>,activeOrder:Order|null,history:Collection<int,Order>,requestKey:string,
     *     repeatQuantities:array<int,int>}
     */
    public function repeat(int $orderId, array $accessible): array
    {
        $source = $this->repository->order($orderId);
        abort_unless(
            $source !== null && in_array((int) $source->bakery_id, $accessible, true),
            404,
        );
        abort_unless($this->service->isOpen(), 422, 'Приём заказов закрыт.');
        $storeId = (int) $source->bakery_id;
        abort_if(
            $this->repository->activeOrder($storeId, $this->service->deliveryDate()) !== null,
            409,
            'Заказ на завтра уже оформлен.',
        );
        $view = $this->home($storeId, $accessible);
        $products = $this->repository->products()->where('is_active', true);
        $prices = $this->repository->prices($storeId);
        $repeatQuantities = [];
        foreach ($source->items as $item) {
            if ($products->contains('id', $item->product_id) && isset($prices[$item->product_id])) {
                $repeatQuantities[$item->product_id] = $item->quantity;
            }
        }

        $view['open'] = true;
        $view['closedReason'] = null;
        $view['activeOrder'] = null;
        $view['repeatQuantities'] = $repeatQuantities;

        return $view;
    }

    /**
     * @param  list<int>  $accessible
     * @return array{order:Order,canCancel:bool,canRepeat:bool}
     */
    public function show(int $orderId, array $accessible): array
    {
        $record = $this->repository->order($orderId);
        abort_unless(
            $record !== null && in_array((int) $record->bakery_id, $accessible, true),
            404,
        );

        return [
            'order' => $record,
            'canCancel' => $record->status === 'accepted' &&
                $this->cutoff->isOpen() &&
                $record->created_at?->timezone('Europe/Moscow')->toDateString() ===
                    now('Europe/Moscow')->toDateString(),
            'canRepeat' => $this->service->isOpen() &&
                $this->repository->activeOrder(
                    (int) $record->bakery_id,
                    $this->service->deliveryDate(),
                ) === null,
        ];
    }
}
