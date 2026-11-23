<?php

namespace Domains\Analytics\Application\Data;

use Domains\Analytics\Application\AnalyticsService;
use Illuminate\Support\Collection;

/**
 * @phpstan-import-type MetricRow from AnalyticsService
 *
 * @phpstan-type AnalyticsResult array{summary:MetricRow,days:list<MetricRow>,stores:list<MetricRow>,products:list<MetricRow>,missing_stores:array<int,string>,updated_at:string}
 * @phpstan-type StockRow array{date:string,item:string,unit:string,balance_milli:int,price_kopecks:int|null,measure_milli:int|null,measure_unit:string|null,value_kopecks:int|null}
 */
readonly class AnalyticsDashboardData
{
    /**
     * @param  array{from:string,to:string,store:int|null,product:int|null}  $filters
     * @param  AnalyticsResult  $data
     * @param  list<StockRow>  $stockRows
     * @param  Collection<int, \stdClass>  $ingredients
     * @param  Collection<int|string, \stdClass>  $ingredientPrices
     * @param  Collection<int, \stdClass>  $stores
     * @param  Collection<int, \stdClass>  $products
     */
    public function __construct(
        public array $filters,
        public array $data,
        public array $stockRows,
        public Collection $ingredients,
        public Collection $ingredientPrices,
        public Collection $stores,
        public Collection $products,
    ) {}

    /** @return array{filters:array{from:string,to:string,store:int|null,product:int|null},data:AnalyticsResult,stockRows:list<StockRow>,ingredients:Collection<int, \stdClass>,ingredientPrices:Collection<int|string, \stdClass>,stores:Collection<int, \stdClass>,products:Collection<int, \stdClass>} */
    public function viewData(): array
    {
        return ['filters' => $this->filters, 'data' => $this->data, 'stockRows' => $this->stockRows, 'ingredients' => $this->ingredients, 'ingredientPrices' => $this->ingredientPrices, 'stores' => $this->stores, 'products' => $this->products];
    }
}
