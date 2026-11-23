<?php

namespace Domains\Invoice\Application\Data;

use Domains\Order\Domain\Models\Order;
use Illuminate\Database\Eloquent\Collection;

/**
 * @phpstan-type ReturnEntry array{quantities:array<int,int>,extras:list<array{product_id:int,
 *     name:string,quantity:int,unit_kopecks:int}>,amount:int}
 */
readonly class SingleInvoiceData
{
    /**
     * @param  Collection<int, Order>  $orders
     * @param  array<int, ReturnEntry>  $returns
     */
    public function __construct(public Collection $orders, public array $returns) {}

    /** @return array{orders: Collection<int, Order>, returns: array<int, ReturnEntry>} */
    public function viewData(): array
    {
        return ['orders' => $this->orders, 'returns' => $this->returns];
    }
}
