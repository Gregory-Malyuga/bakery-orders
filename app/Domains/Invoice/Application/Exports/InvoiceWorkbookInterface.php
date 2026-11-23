<?php

namespace Domains\Invoice\Application\Exports;

use Domains\Order\Domain\Models\Order;
use Domains\Product\Domain\Models\Product;

/** @phpstan-import-type GroupInvoice from \Domains\StoreGroup\Application\GroupInvoiceService */
interface InvoiceWorkbookInterface
{
    /** @param iterable<Order> $orders
     * @param  iterable<Product>  $products
     * @param  list<GroupInvoice>  $groups
     */
    public function create(
        iterable $orders,
        iterable $products,
        array $groups = [],
        ?string $date = null,
        ?string $generatedAt = null,
    ): string;
}
