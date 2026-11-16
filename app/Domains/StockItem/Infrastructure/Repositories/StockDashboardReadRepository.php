<?php

namespace Domains\StockItem\Infrastructure\Repositories;

use Domains\StockItem\Application\Repositories\StockDashboardReadRepositoryInterface;
use Domains\StockPrice\Application\StockPrices;
use Domains\StockMovement\Application\Repositories\StockMovementDashboardRepositoryInterface;
use Domains\StockPriceChange\Application\Repositories\StockPriceChangeDashboardRepositoryInterface;
use Domains\StockPrice\Application\Repositories\StockDiscrepancyPriceReadRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

readonly class StockDashboardReadRepository implements StockDashboardReadRepositoryInterface
{
    public function __construct(
        private StockPrices $stockPrices,
        private StockMovementDashboardRepositoryInterface $stockMovements,
        private StockPriceChangeDashboardRepositoryInterface $stockChanges,
        private StockDiscrepancyPriceReadRepositoryInterface $discrepancyPriceRead,
    ) {}

    /** @return array<string, mixed> */
    public function data(string $date): array
    {
        $items = DB::table('bakery_stock_items')->orderBy('kind')->orderBy('name')->get();
        $today = now('Europe/Moscow')->toDateString();
        $tomorrow = now('Europe/Moscow')->addDay()->toDateString();
        $prices = $this->stockPrices->forDate($date);
        $tomorrowPrices = $this->stockChanges->currentPrices($tomorrow);
        $priceHistory = $this->stockChanges->history();
        $balances = $this->stockMovements->balances($date);
        $movements = $this->stockMovements->movements($date);
        $discrepancies = $this->stockMovements->discrepancies($date);
        $discrepancyPrices = $this->discrepancyPriceRead->forDiscrepancies($discrepancies);

        return compact('items', 'balances', 'movements', 'discrepancies', 'discrepancyPrices', 'date', 'today', 'tomorrow', 'prices', 'tomorrowPrices', 'priceHistory');
    }
}
