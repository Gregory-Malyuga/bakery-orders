<?php

namespace Domains\Invoice\Application\Data;

use Domains\Order\Domain\Models\Order;
use Domains\StoreGroup\Application\GroupInvoiceService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * @phpstan-import-type GroupInvoice from GroupInvoiceService
 *
 * @phpstan-type ReturnEntry array{quantities:array<int,int>,extras:list<array{product_id:int,name:string,quantity:int,unit_kopecks:int}>,amount:int}
 */
readonly class BatchInvoiceData
{
    /**
     * @param  Collection<int, Order>  $orders
     * @param  array<int, ReturnEntry>  $returns
     * @param  list<GroupInvoice>  $groups
     */
    public function __construct(
        public Collection $orders,
        public array $returns,
        public array $groups,
        public string $date,
        public Carbon $generatedAt,
    ) {}

    /** @return array{orders: Collection<int, Order>, returns: array<int, ReturnEntry>, groups: list<GroupInvoice>, date: string, generatedAt: Carbon} */
    public function viewData(): array
    {
        return ['orders' => $this->orders, 'returns' => $this->returns, 'groups' => $this->groups, 'date' => $this->date, 'generatedAt' => $this->generatedAt];
    }
}
