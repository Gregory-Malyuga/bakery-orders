<?php

namespace Domains\Catalog\Application\Data;

use Domains\Product\Domain\Models\Product;
use Domains\Store\Domain\Models\Store;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

readonly class CatalogProductData
{
    /**
     * @param  EloquentCollection<int, Store>  $stores
     * @param  array<int, int|null>  $prices
     * @param  array<int, bool>  $unavailable
     * @param  Collection<int, \stdClass>  $history
     * @param  Collection<int|string, Collection<int, \stdClass>>  $historyItems
     * @param  list<int|string>  $reversed
     */
    public function __construct(
        public Product $item,
        public EloquentCollection $stores,
        public array $prices,
        public array $unavailable,
        public Collection $history,
        public Collection $historyItems,
        public array $reversed,
    ) {}

    /** @return array{item:Product,stores:EloquentCollection<int, Store>,prices:array<int,int|null>,unavailable:array<int,bool>,history:Collection<int,\stdClass>,historyItems:Collection<int|string,Collection<int,\stdClass>>,reversed:list<int|string>} */
    public function viewData(): array
    {
        return ['item' => $this->item, 'stores' => $this->stores, 'prices' => $this->prices, 'unavailable' => $this->unavailable, 'history' => $this->history, 'historyItems' => $this->historyItems, 'reversed' => $this->reversed];
    }
}
