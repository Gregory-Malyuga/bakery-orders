<?php

namespace Domains\Order\Application\Data;

use Domains\Order\Domain\Models\Order;
use Domains\Product\Domain\Models\Product;
use Domains\Store\Domain\Models\Store;
use Illuminate\Database\Eloquent\Collection;

readonly class StoreOrderHomeData
{
    /**
     * @param  Collection<int, Store>  $accessibleStores
     * @param  array{order_date:string,delivery_date:string}  $nextWindow
     * @param  Collection<int, Product>  $products
     * @param  array<int, int>  $prices
     * @param  array<int, int>  $minimums
     * @param  Collection<int, Order>  $history
     * @param  array<int, int>  $repeatQuantities
     */
    public function __construct(
        public Store $store,
        public Collection $accessibleStores,
        public string $date,
        public bool $open,
        public ?string $closedReason,
        public array $nextWindow,
        public string $orderCutoff,
        public Collection $products,
        public array $prices,
        public array $minimums,
        public ?Order $activeOrder,
        public Collection $history,
        public string $requestKey,
        public array $repeatQuantities,
    ) {}

    /** @return array{store:Store,accessibleStores:Collection<int,Store>,date:string,open:bool,closedReason:string|null,nextWindow:array{order_date:string,delivery_date:string},orderCutoff:string,products:Collection<int,Product>,prices:array<int,int>,minimums:array<int,int>,activeOrder:Order|null,history:Collection<int,Order>,requestKey:string,repeatQuantities:array<int,int>} */
    public function viewData(): array
    {
        return ['store' => $this->store, 'accessibleStores' => $this->accessibleStores, 'date' => $this->date, 'open' => $this->open, 'closedReason' => $this->closedReason, 'nextWindow' => $this->nextWindow, 'orderCutoff' => $this->orderCutoff, 'products' => $this->products, 'prices' => $this->prices, 'minimums' => $this->minimums, 'activeOrder' => $this->activeOrder, 'history' => $this->history, 'requestKey' => $this->requestKey, 'repeatQuantities' => $this->repeatQuantities];
    }
}
