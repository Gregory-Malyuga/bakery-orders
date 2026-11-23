<?php

namespace Domains\Order\Application\Data;

use Domains\Order\Domain\Models\Order;
use Domains\Product\Domain\Models\Product;
use Illuminate\Database\Eloquent\Collection;

readonly class AdminOrderEditData
{
    /**
     * @param  Collection<int, Product>  $products
     * @param  array<int, int>  $prices
     */
    public function __construct(
        public Order $order,
        public Collection $products,
        public array $prices,
    ) {}

    /** @return array{order: Order, products: Collection<int, Product>, prices: array<int, int>} */
    public function viewData(): array
    {
        return ['order' => $this->order, 'products' => $this->products, 'prices' => $this->prices];
    }
}
