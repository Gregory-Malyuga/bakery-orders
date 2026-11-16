<?php

namespace Domains\Bakery\Application\Exports;

use Domains\Bakery\Domain\Models\Order;
use Domains\Bakery\Domain\Models\Product;

/** @phpstan-import-type GroupInvoice from \Domains\Bakery\Application\GroupInvoiceService */
interface InvoiceWorkbookInterface
{
    /** @param iterable<Order> $orders
     * @param  iterable<Product>  $products
     * @param  list<GroupInvoice>  $groups
     */
    public function create(iterable $orders, iterable $products, array $groups = [], ?string $date = null, ?string $generatedAt = null): string;
}
