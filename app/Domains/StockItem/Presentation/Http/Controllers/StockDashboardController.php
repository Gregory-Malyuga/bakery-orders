<?php

namespace Domains\StockItem\Presentation\Http\Controllers;

use Domains\StockItem\Application\Commands\SyncCatalog\SyncCatalogCommand;
use Domains\StockItem\Application\Commands\SyncCatalog\SyncCatalogHandler;
use Domains\StockItem\Application\Queries\Dashboard\StockDashboardHandler;
use Domains\StockItem\Application\Queries\Dashboard\StockDashboardQuery;
use Illuminate\Http\Request;
use Illuminate\View\View;

readonly class StockDashboardController
{
    public function __construct(
        private SyncCatalogHandler $syncCatalog,
        private StockDashboardHandler $dashboard,
    ) {}

    public function index(Request $request): View
    {
        $this->syncCatalog->handle(new SyncCatalogCommand);

        /** @var array{date?:string} $filters */
        $filters = $request->validate(['date' => ['nullable', 'date_format:Y-m-d']]);
        $date = $filters['date'] ?? now('Europe/Moscow')->toDateString();

        return view('bakery.stock', $this->dashboard->handle(new StockDashboardQuery($date))->data);
    }
}
