<?php

namespace Domains\Bakery\Application\Exports;

use Domains\Bakery\Domain\Models\Order;
use Domains\Bakery\Domain\Models\Product;

interface InvoiceWorkbookInterface
{
    /** @param iterable<Order> $orders
     * @param  iterable<Product>  $products
     */
    public function create(iterable $orders, iterable $products): string;
}
