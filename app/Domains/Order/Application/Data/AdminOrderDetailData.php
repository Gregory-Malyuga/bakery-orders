<?php

namespace Domains\Order\Application\Data;

use Domains\Order\Domain\Models\Order;
use Domains\Product\Domain\Models\Product;
use Illuminate\Support\Collection;

readonly class AdminOrderDetailData
{
    /**
     * @param  Collection<int, \stdClass>  $returns
     * @param  Collection<int, \stdClass>  $adjustments
     * @param  Collection<int, Product>  $returnProducts
     */
    public function __construct(
        public Order $order,
        public Collection $returns,
        public Collection $adjustments,
        public Collection $returnProducts,
        public int $returnAmount,
    ) {}

    /** @return array{order: Order, returns: Collection<int, \stdClass>, adjustments: Collection<int, \stdClass>, returnProducts: Collection<int, Product>, returnAmount: int} */
    public function viewData(): array
    {
        return ['order' => $this->order, 'returns' => $this->returns, 'adjustments' => $this->adjustments, 'returnProducts' => $this->returnProducts, 'returnAmount' => $this->returnAmount];
    }
}
