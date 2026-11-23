<?php

namespace Domains\Production\Application\Data;

use Domains\Order\Domain\Models\Order;
use Domains\Product\Domain\Models\Product;
use Domains\Store\Domain\Models\Store;
use Domains\StoreGroup\Application\GroupInvoiceService;
use Illuminate\Database\Eloquent\Collection;

/**
 * @phpstan-import-type GroupInvoice from GroupInvoiceService
 *
 * @phpstan-type ReturnEntry array{quantities:array<int,int>,extras:list<array{product_id:int,name:string,quantity:int,unit_kopecks:int}>,amount:int}
 * @phpstan-type PackagePlan array{one:int,two:int,three:int,five:int,loose:int}
 * @phpstan-type ProductionResult array{dough_grams:int|float,main_dough:array<string,float>,samsa_count:int,samsa_batch_size:int|float,samsa_dough:array<string,float>,ingredients:array<string,int|float>,other_ingredients:array<string,array<string,int|float>>,recipes:array<string,array{quantity:int,ingredients:array<string,int|float>,other_ingredients:array<string,array{quantity:int|float,unit:string}>}>,without_recipe:list<string>}
 */
readonly class ProductionDashboardData
{
    /**
     * @param  Collection<int, Order>  $orders
     * @param  Collection<int, Order>  $visibleOrders
     * @param  array<int, ReturnEntry>  $orderReturns
     * @param  list<GroupInvoice>  $groups
     * @param  Collection<int, Store>  $activeStores
     * @param  Collection<int, Store>  $matrixStores
     * @param  Collection<int, Product>  $products
     * @param  array<int, array<int, int>>  $quantities
     * @param  array<int, int>  $totals
     * @param  array<int, PackagePlan>  $packaging
     * @param  array<int, PackagePlan>  $labelPackaging
     * @param  array<int, int>  $labels
     * @param  list<array{name:string,groups:list<string>,packages:int}>  $excludedStores
     * @param  ProductionResult  $production
     * @param  list<int>  $orderedStoreIds
     */
    public function __construct(
        public string $date,
        public string $tab,
        public Collection $orders,
        public Collection $visibleOrders,
        public array $orderReturns,
        public array $groups,
        public int $page,
        public int $pageCount,
        public Collection $activeStores,
        public Collection $matrixStores,
        public Collection $products,
        public array $quantities,
        public array $totals,
        public array $packaging,
        public array $labelPackaging,
        public array $labels,
        public int $labelTotal,
        public int $packageTotal,
        public array $excludedStores,
        public int $productionTotal,
        public array $production,
        public array $orderedStoreIds,
    ) {}

    /** @return array{date:string,tab:string,orders:Collection<int,Order>,visibleOrders:Collection<int,Order>,orderReturns:array<int,ReturnEntry>,groups:list<GroupInvoice>,page:int,pageCount:int,activeStores:Collection<int,Store>,matrixStores:Collection<int,Store>,products:Collection<int,Product>,quantities:array<int,array<int,int>>,totals:array<int,int>,packaging:array<int,PackagePlan>,labelPackaging:array<int,PackagePlan>,labels:array<int,int>,labelTotal:int,packageTotal:int,excludedStores:list<array{name:string,groups:list<string>,packages:int}>,productionTotal:int,production:ProductionResult,orderedStoreIds:list<int>} */
    public function viewData(): array
    {
        return ['date' => $this->date, 'tab' => $this->tab, 'orders' => $this->orders, 'visibleOrders' => $this->visibleOrders, 'orderReturns' => $this->orderReturns, 'groups' => $this->groups, 'page' => $this->page, 'pageCount' => $this->pageCount, 'activeStores' => $this->activeStores, 'matrixStores' => $this->matrixStores, 'products' => $this->products, 'quantities' => $this->quantities, 'totals' => $this->totals, 'packaging' => $this->packaging, 'labelPackaging' => $this->labelPackaging, 'labels' => $this->labels, 'labelTotal' => $this->labelTotal, 'packageTotal' => $this->packageTotal, 'excludedStores' => $this->excludedStores, 'productionTotal' => $this->productionTotal, 'production' => $this->production, 'orderedStoreIds' => $this->orderedStoreIds];
    }
}
