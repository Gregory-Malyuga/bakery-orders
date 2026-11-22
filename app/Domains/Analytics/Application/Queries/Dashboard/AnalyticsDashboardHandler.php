<?php

namespace Domains\Analytics\Application\Queries\Dashboard;

use Domains\Analytics\Application\AnalyticsService;
use Domains\Analytics\Application\Data\AnalyticsDashboardData;
use Domains\Product\Application\Repositories\ProductOptionsReadRepositoryInterface;
use Domains\StockItem\Application\Repositories\IngredientStockReadRepositoryInterface;
use Domains\StockPrice\Application\StockPrices;
use Domains\Store\Application\Repositories\StoreAnalyticsReadRepositoryInterface;
use Shared\Bus\QueryHandlerInterface;

readonly class AnalyticsDashboardHandler implements QueryHandlerInterface
{
    public function __construct(
        private AnalyticsService $analytics,
        private StockPrices $stockPrices,
        private IngredientStockReadRepositoryInterface $stockItems,
        private StoreAnalyticsReadRepositoryInterface $stores,
        private ProductOptionsReadRepositoryInterface $products,
    ) {}

    public function handle(object $message): AnalyticsDashboardData
    {
        assert($message instanceof AnalyticsDashboardQuery);

        return new AnalyticsDashboardData([
            'filters' => [
                'from' => $message->from,
                'to' => $message->to,
                'store' => $message->storeId,
                'product' => $message->productId,
            ],
            'data' => $this->analytics->build($message->from, $message->to, $message->storeId, $message->productId),
            'stockRows' => $this->stockPrices->dailyRows($message->from, $message->to),
            'ingredients' => $this->stockItems->ingredients(),
            'ingredientPrices' => $this->stockPrices->forDate($message->to),
            'stores' => $this->stores->storeOptions(),
            'products' => $this->products->productOptions(),
        ]);
    }
}
