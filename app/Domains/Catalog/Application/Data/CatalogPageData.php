<?php

namespace Domains\Catalog\Application\Data;

use Domains\Product\Domain\Models\Product;
use Domains\Store\Domain\Models\Store;
use Illuminate\Database\Eloquent\Collection;

readonly class CatalogPageData
{
    /**
     * @param  Collection<int, Store>  $stores
     * @param  Collection<int, Store>  $visibleStores
     * @param  Collection<int, Product>  $products
     * @param  list<array{id:int,bakery_id:int|null,store_ids:list<int>,name:string,email:string,status:string}>  $accounts
     * @param  list<array{id:int,bakery_id:int|null,store_ids:list<int>,name:string,email:string,status:string}>  $visibleAccounts
     * @param  array<int, int>  $storeOwners
     * @param  array<int, bool>  $unavailableProducts
     * @param  array<int, int>  $prices
     * @param  array<int, int>  $minimums
     */
    public function __construct(
        public Collection $stores,
        public Collection $visibleStores,
        public Collection $products,
        public int $selectedStore,
        public array $accounts,
        public array $visibleAccounts,
        public array $storeOwners,
        public array $unavailableProducts,
        public string $tab,
        public int $page,
        public int $pageCount,
        public string $search,
        public array $prices,
        public array $minimums,
        public string $orderCutoff,
    ) {}

    /** @return array{stores:Collection<int, Store>,visibleStores:Collection<int, Store>,products:Collection<int, Product>,selectedStore:int,accounts:list<array{id:int,bakery_id:int|null,store_ids:list<int>,name:string,email:string,status:string}>,visibleAccounts:list<array{id:int,bakery_id:int|null,store_ids:list<int>,name:string,email:string,status:string}>,storeOwners:array<int,int>,unavailableProducts:array<int,bool>,tab:string,page:int,pageCount:int,search:string,prices:array<int,int>,minimums:array<int,int>,orderCutoff:string} */
    public function viewData(): array
    {
        return ['stores' => $this->stores, 'visibleStores' => $this->visibleStores, 'products' => $this->products, 'selectedStore' => $this->selectedStore, 'accounts' => $this->accounts, 'visibleAccounts' => $this->visibleAccounts, 'storeOwners' => $this->storeOwners, 'unavailableProducts' => $this->unavailableProducts, 'tab' => $this->tab, 'page' => $this->page, 'pageCount' => $this->pageCount, 'search' => $this->search, 'prices' => $this->prices, 'minimums' => $this->minimums, 'orderCutoff' => $this->orderCutoff];
    }
}
